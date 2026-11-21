<script>
    window.rentdesk = @js([
        'locale' => str_replace('_', '-', app()->getLocale()),
        'dateFormat' => config('rentdesk.date_format'),
        'timeFormat' => str_replace('A', 'K', config('rentdesk.time_format')),
        'text' => ['yes' => __('Yes'), 'cancel' => __('Cancel'), 'required' => __('This field is required.')],
    ]);
</script>
