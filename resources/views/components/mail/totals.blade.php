@props(['money'])

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 16px;">
    @if ($money['has_discount'])
        <x-mail.row :label="__('mail.totals.before_discount')" :value="$money['before_discount']" />
        <x-mail.row :label="__('mail.totals.discount')" :value="$money['discount']" />
    @endif

    <x-mail.row :label="__('mail.totals.subtotal')" :value="$money['subtotal']" />
    <x-mail.row :label="__('mail.totals.vat', ['rate' => $money['rate']])" :value="$money['tax']" />
    <x-mail.row :label="__('mail.totals.total')" :value="$money['total']" total />
</table>
