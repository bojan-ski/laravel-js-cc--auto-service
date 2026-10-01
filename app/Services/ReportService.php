<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\JobItemType;
use App\Enums\Locale;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\User;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Traits\Localizable;

class ReportService
{
    use Localizable;

    private const BOM = "\xEF\xBB\xBF";

    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public function __construct(private CsvReader $csv) {}

    /**
     * @return array{invoices: int, before_discount: string, discount: string, subtotal: string, vat: string, total: string, has_discount: bool, voided: int}
     */
    public function revenue(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $void = InvoiceStatus::Void->value;

        $row = Invoice::query()
            ->issuedBetween($from, $to)
            ->toBase()
            ->selectRaw('sum(status <> ?) as invoices', [$void])
            ->selectRaw('sum(status = ?) as voided', [$void])
            ->selectRaw('sum(case when status <> ? then subtotal end) as subtotal', [$void])
            ->selectRaw('sum(case when status <> ? then discount_total end) as discount', [$void])
            ->selectRaw('sum(case when status <> ? then tax_amount end) as vat', [$void])
            ->selectRaw('sum(case when status <> ? then total end) as total', [$void])
            ->first();

        $subtotal = $this->sum($row->subtotal);
        $discount = $this->sum($row->discount);

        return [
            'invoices' => (int) $row->invoices,
            'before_discount' => bcadd($subtotal, $discount, 2),
            'discount' => $discount,
            'subtotal' => $subtotal,
            'vat' => $this->sum($row->vat),
            'total' => $this->sum($row->total),
            'has_discount' => bccomp($discount, '0', 2) > 0,
            'voided' => (int) $row->voided,
        ];
    }

    /**
     * @return list<array{rate: string, base: string, vat: string}>
     */
    public function vatByRate(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->counted($from, $to)
            ->toBase()
            ->select('tax_rate')
            ->selectRaw('sum(subtotal) as base, sum(tax_amount) as vat')
            ->groupBy('tax_rate')
            ->orderBy('tax_rate')
            ->get()
            ->map(fn (object $row): array => [
                'rate' => Money::trimZeros((string) $row->tax_rate),
                'base' => $this->sum($row->base),
                'vat' => $this->sum($row->vat),
            ])
            ->all();
    }

    /**
     * @return list<array{key: string, label: string, invoices: int, amount: string}>
     */
    public function payments(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $groups = $this->counted($from, $to)
            ->toBase()
            ->select(['status', 'payment_method'])
            ->selectRaw('count(*) as invoices, sum(total) as amount')
            ->groupBy('status', 'payment_method')
            ->get();

        $buckets = [];

        foreach ($groups as $group) {
            $key = match (true) {
                $group->status === InvoiceStatus::Unpaid->value => 'unpaid',
                $group->payment_method === null => 'unrecorded',
                default => $group->payment_method,
            };

            $buckets[$key] = [
                'invoices' => ($buckets[$key]['invoices'] ?? 0) + (int) $group->invoices,
                'amount' => bcadd($buckets[$key]['amount'] ?? '0.00', $this->sum($group->amount), 2),
            ];
        }

        $labels = [];

        foreach (PaymentMethod::cases() as $method) {
            $labels[$method->value] = $method->label();
        }

        $labels['unrecorded'] = __('reports.payments.unrecorded');
        $labels['unpaid'] = __('reports.payments.unpaid');

        $rows = [];

        foreach ($labels as $key => $label) {
            if (isset($buckets[$key])) {
                $rows[] = ['key' => $key, 'label' => $label, ...$buckets[$key]];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{user: User, hours: string, amount: string}>
     */
    public function laborByMechanic(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->invoicedLines($from, $to, JobItemType::Labor)
            ->addSelect('user_id')
            ->with('user:id,name,deleted_at')
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $lines): array => [
                'user' => $lines->first()->user,
                'hours' => $lines->reduce(fn (string $sum, JobItem $line): string => bcadd($sum, $line->quantity, 2), '0.00'),
                'amount' => $this->lineTotals($lines),
            ])
            ->sort(fn (array $a, array $b): int => bccomp($b['hours'], $a['hours'], 2) ?: strcmp($a['user']->name, $b['user']->name))
            ->values()
            ->all();
    }

    /**
     * @return array{rows: list<array{part: Part, quantity: int, revenue: string, cost: ?string, margin: ?string}>, quantity: int, revenue: string, cost: ?string, margin: ?string, without_cost: int}
     */
    public function partsSold(CarbonImmutable $from, CarbonImmutable $to, bool $withMargin): array
    {
        $columns = $withMargin
            ? ['id', 'sku', 'name', 'cost_price', 'deleted_at']
            : ['id', 'sku', 'name', 'deleted_at'];

        $rows = $this->invoicedLines($from, $to, JobItemType::Part)
            ->addSelect('part_id')
            ->with(['part' => fn ($query) => $query->select($columns)])
            ->get()
            ->groupBy('part_id')
            ->map(function (Collection $lines) use ($withMargin): array {
                $part = $lines->first()->part;
                $quantity = $lines->sum(fn (JobItem $line): int => (int) $line->quantity);
                $revenue = $this->lineTotals($lines);
                $cost = $withMargin && $part->cost_price !== null
                    ? bcmul((string) $quantity, $part->cost_price, 2)
                    : null;

                return [
                    'part' => $part,
                    'quantity' => $quantity,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'margin' => $cost === null ? null : bcsub($revenue, $cost, 2),
                ];
            })
            ->sort(fn (array $a, array $b): int => bccomp($b['revenue'], $a['revenue'], 2) ?: strcmp($a['part']->sku, $b['part']->sku))
            ->values();

        $costed = $rows->whereNotNull('cost');

        return [
            'rows' => $rows->all(),
            'quantity' => $rows->sum('quantity'),
            'revenue' => $rows->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['revenue'], 2), '0.00'),
            'cost' => $withMargin ? $costed->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['cost'], 2), '0.00') : null,
            'margin' => $withMargin ? $costed->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['margin'], 2), '0.00') : null,
            'without_cost' => $withMargin ? $rows->count() - $costed->count() : 0,
        ];
    }

    /**
     * @return Generator<int, string>
     */
    public function exportLines(CarbonImmutable $from, CarbonImmutable $to, int $chunkSize = 1000): Generator
    {
        $shop = Locale::shop()->value;

        yield $this->withLocale($shop, fn (): string => self::BOM.$this->csv->toCsvLine($this->exportHeaders(), ';'));

        $invoices = Invoice::query()
            ->join('jobs', 'jobs.id', '=', 'invoices.job_id')
            ->issuedBetween($from, $to)
            ->select([
                'invoices.id as id',
                'invoices.number',
                'invoices.status',
                'invoices.issued_at',
                'jobs.reference as job_reference',
                'invoices.buyer_name',
                'invoices.buyer_address',
                'invoices.buyer_tax_id',
                'invoices.buyer_registration_number',
                'invoices.subtotal',
                'invoices.discount_total',
                'invoices.tax_rate',
                'invoices.tax_amount',
                'invoices.total',
                'invoices.payment_method',
                'invoices.paid_at',
                'invoices.voided_at',
            ])
            ->lazyById($chunkSize, 'invoices.id', 'id');

        foreach ($invoices as $invoice) {
            yield $this->withLocale($shop, fn (): string => $this->csv->toCsvLine($this->exportRow($invoice), ';'));
        }
    }

    /**
     * @return list<string>
     */
    private function exportHeaders(): array
    {
        return [
            __('reports.csv.number'),
            __('reports.csv.issued_on'),
            __('reports.csv.status'),
            __('reports.csv.job'),
            __('reports.csv.buyer_name'),
            __('reports.csv.buyer_address'),
            __('reports.csv.buyer_tax_id'),
            __('reports.csv.buyer_registration_number'),
            __('reports.csv.before_discount'),
            __('reports.csv.discount'),
            __('reports.csv.subtotal'),
            __('reports.csv.vat_rate'),
            __('reports.csv.vat'),
            __('reports.csv.total'),
            __('reports.csv.payment_method'),
            __('reports.csv.paid_on'),
            __('reports.csv.voided_on'),
        ];
    }

    /**
     * @return list<string>
     */
    private function exportRow(Invoice $invoice): array
    {
        return [
            $this->textCell($invoice->number),
            $invoice->issued_at->format('Y-m-d'),
            $this->textCell($invoice->status->label()),
            $this->textCell($invoice->job_reference),
            $this->textCell($invoice->buyer_name),
            $this->textCell($invoice->buyer_address),
            $this->textCell($invoice->buyer_tax_id),
            $this->textCell($invoice->buyer_registration_number),
            Money::csv(bcadd($invoice->subtotal, $invoice->discount_total, 2)),
            Money::csv($invoice->discount_total),
            Money::csv($invoice->subtotal),
            Money::csv($invoice->tax_rate),
            Money::csv($invoice->tax_amount),
            Money::csv($invoice->total),
            $this->textCell($invoice->payment_method?->label()),
            $invoice->paid_at?->format('Y-m-d') ?? '',
            $invoice->voided_at?->format('Y-m-d') ?? '',
        ];
    }

    private function textCell(?string $value): string
    {
        $value ??= '';

        return $value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true) ? "'".$value : $value;
    }

    /**
     * @return Builder<Invoice>
     */
    private function counted(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Invoice::query()->notVoid()->issuedBetween($from, $to);
    }

    /**
     * @return Builder<JobItem>
     */
    private function invoicedLines(CarbonImmutable $from, CarbonImmutable $to, JobItemType $type): Builder
    {
        return JobItem::query()
            ->select(['id', 'job_id', 'type', 'quantity', 'unit_price', 'discount_percent'])
            ->where('type', $type)
            ->whereIn('job_id', $this->counted($from, $to)->select('job_id'));
    }

    /**
     * @param  Collection<int, JobItem>  $lines
     */
    private function lineTotals(Collection $lines): string
    {
        return $lines->reduce(fn (string $sum, JobItem $line): string => bcadd($sum, $line->lineTotal(), 2), '0.00');
    }

    private function sum(mixed $value): string
    {
        return $value === null ? '0.00' : (string) $value;
    }
}
