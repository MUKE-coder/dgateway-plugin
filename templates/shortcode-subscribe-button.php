<?php defined('ABSPATH') || exit; ?>
<button type="button"
    class="dgw-subscribe-btn <?php echo esc_attr($atts['class']); ?>"
    data-dgw-subscribe="1"
    data-dgw-plan-id="<?php echo esc_attr($atts['plan_id']); ?>"
    data-dgw-description="<?php echo esc_attr($atts['description']); ?>"
>
    <?php echo esc_html($atts['label']); ?>
</button>
