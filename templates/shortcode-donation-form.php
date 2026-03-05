<?php defined('ABSPATH') || exit; ?>
<div class="dgw-donation-form" id="dgw-donation-<?php echo esc_attr(wp_unique_id('dgw-')); ?>">
    <?php if (!empty($atts['title'])) : ?>
        <h3 class="dgw-donation-title"><?php echo esc_html($atts['title']); ?></h3>
    <?php endif; ?>

    <?php if (!empty($atts['description'])) : ?>
        <p class="dgw-donation-desc"><?php echo esc_html($atts['description']); ?></p>
    <?php endif; ?>

    <!-- Preset Amounts -->
    <?php if (!empty($atts['amounts_array'])) : ?>
        <div class="dgw-donation-amounts">
            <?php foreach ($atts['amounts_array'] as $preset) : ?>
                <button type="button" class="dgw-donation-amount-btn" data-amount="<?php echo esc_attr($preset); ?>">
                    <?php echo esc_html($atts['currency'] . ' ' . number_format((float) $preset)); ?>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Custom Amount -->
    <?php if ($atts['allow_custom'] === 'yes') : ?>
        <div class="dgw-donation-custom">
            <input type="number" class="dgw-donation-custom-input"
                   placeholder="<?php esc_attr_e('Enter custom amount', 'dgateway'); ?>"
                   min="1" step="any" />
        </div>
    <?php endif; ?>

    <!-- Optional Fields -->
    <div class="dgw-donation-fields">
        <?php if ($atts['collect_name'] === 'yes') : ?>
            <input type="text" class="dgw-donation-name" placeholder="<?php esc_attr_e('Your Name', 'dgateway'); ?>" />
        <?php endif; ?>
        <?php if ($atts['collect_email'] === 'yes') : ?>
            <input type="email" class="dgw-donation-email" placeholder="<?php esc_attr_e('Your Email', 'dgateway'); ?>" />
        <?php endif; ?>
    </div>

    <!-- Submit -->
    <button type="button" class="dgw-donation-submit" disabled>
        <?php esc_html_e('Donate', 'dgateway'); ?>
    </button>
</div>

<script>
(function() {
    var form = document.currentScript.previousElementSibling;
    var selectedAmount = 0;
    var currency = <?php echo wp_json_encode($atts['currency']); ?>;
    var successUrl = <?php echo wp_json_encode($atts['success_url']); ?>;
    var submitBtn = form.querySelector('.dgw-donation-submit');
    var customInput = form.querySelector('.dgw-donation-custom-input');
    var presetBtns = form.querySelectorAll('.dgw-donation-amount-btn');

    function updateButton() {
        if (selectedAmount > 0) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Donate ' + currency + ' ' + Number(selectedAmount).toLocaleString();
        } else {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Donate';
        }
    }

    presetBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            presetBtns.forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            selectedAmount = parseFloat(btn.getAttribute('data-amount'));
            if (customInput) customInput.value = '';
            updateButton();
        });
    });

    if (customInput) {
        customInput.addEventListener('input', function() {
            presetBtns.forEach(function(b) { b.classList.remove('active'); });
            selectedAmount = parseFloat(customInput.value) || 0;
            updateButton();
        });
    }

    submitBtn.addEventListener('click', function() {
        if (selectedAmount <= 0) return;

        var description = 'Donation';
        var nameEl = form.querySelector('.dgw-donation-name');
        if (nameEl && nameEl.value) {
            description += ' from ' + nameEl.value;
        }

        DGW_Modal.open({
            amount: selectedAmount,
            currency: currency,
            description: description,
            successUrl: successUrl,
        });
    });
})();
</script>
