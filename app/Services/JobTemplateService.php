<?php

namespace App\Services;

use App\Enums\JobItemType;
use App\Models\JobTemplate;
use App\Models\JobTemplateItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class JobTemplateService
{
    public const int PER_PAGE = 25;

    /**
     * @return LengthAwarePaginator<int, JobTemplate>
     */
    public function paginate(bool $withTrashed): LengthAwarePaginator
    {
        return JobTemplate::query()
            ->when($withTrashed, fn (Builder $query) => $query->withTrashed())
            ->withCount('items')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @return array<int, string>
     */
    public function pickerOptions(): array
    {
        return JobTemplate::query()
            ->select(['id', 'name'])
            ->has('items')
            ->withCount('items')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (JobTemplate $template): array => [
                $template->getKey() => __('templates.apply.option', [
                    'name' => $template->name,
                    'lines' => trans_choice('templates.lines_count', $template->items_count, ['count' => $template->items_count]),
                ]),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): JobTemplate
    {
        $template = new JobTemplate;

        $template->fill($attributes);
        $template->save();

        return $template;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(JobTemplate $template, array $attributes): JobTemplate
    {
        $template->update($attributes);

        return $template;
    }

    public function delete(JobTemplate $template): void
    {
        $template->delete();
    }

    public function restore(JobTemplate $template): JobTemplate
    {
        $template->restore();

        return $template;
    }

    /**
     * @return Collection<int, JobTemplateItem>
     */
    public function linesFor(JobTemplate $template): Collection
    {
        return $template->items()
            ->with('part:id,sku,name,deleted_at')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addItem(JobTemplate $template, JobItemType $type, array $attributes): JobTemplateItem
    {
        $item = new JobTemplateItem;

        $item->fill($attributes);

        $item->job_template_id = $template->getKey();
        $item->type = $type;
        $item->part_id = $type === JobItemType::Part ? (int) $attributes['part_id'] : null;
        $item->description = $type === JobItemType::Part ? null : $item->description;
        $item->position = ((int) $template->items()->max('position')) + 1;

        $item->save();

        return $item;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateItem(JobTemplateItem $item, array $attributes): JobTemplateItem
    {
        $item->fill($attributes);

        if ($item->isPart()) {
            $item->part_id = (int) $attributes['part_id'];
            $item->description = null;
        }

        $item->save();

        return $item;
    }

    public function removeItem(JobTemplateItem $item): void
    {
        $item->delete();
    }
}
