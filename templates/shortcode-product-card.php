<?php defined('ABSPATH') || exit; ?>
<div class="dgw-product-card">
    <?php if (!empty($atts['image'])) : ?>
        <img src="<?php echo esc_url($atts['image']); ?>"
             alt="<?php echo esc_attr($atts['name']); ?>"
             class="dgw-product-image" />
    <?php endif; ?>

    <div class="dgw-product-body">
        <h4 class="dgw-product-name"><?php echo esc_html($atts['name']); ?></h4>

        <?php if (!empty($atts['description'])) : ?>
            <p class="dgw-product-desc"><?php echo esc_html($atts['description']); ?></p>
        <?php endif; ?>

        <div class="dgw-product-footer">
            <span class="dgw-product-price">
                <?php echo esc_html($atts['currency'] . ' ' . number_format((float) $atts['price'])); ?>
            </span>
            <button type="button" class="dgw-product-buy"
                data-dgw-amount="<?php echo esc_attr($atts['price']); ?>"
                data-dgw-currency="<?php echo esc_attr($atts['currency']); ?>"
                data-dgw-description="<?php echo esc_attr($atts['name']); ?>"
                data-dgw-success-url="<?php echo esc_attr($atts['success_url']); ?>">
                <?php echo esc_html($atts['button_label']); ?>
            </button>
        </div>
    </div>
</div>
