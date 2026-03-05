<?php defined('ABSPATH') || exit; ?>
<button type="button"
    class="dgw-pay-button <?php echo esc_attr($atts['class']); ?>"
    data-dgw-amount="<?php echo esc_attr($atts['amount']); ?>"
    data-dgw-currency="<?php echo esc_attr($atts['currency']); ?>"
    data-dgw-provider="<?php echo esc_attr($atts['provider']); ?>"
    data-dgw-description="<?php echo esc_attr($atts['description']); ?>"
    data-dgw-success-url="<?php echo esc_attr($atts['success_url']); ?>">
    <?php echo esc_html($atts['label']); ?>
</button>
