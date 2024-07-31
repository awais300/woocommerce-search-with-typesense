<div class="wrap">
    <h1>Typesense Search Settings</h1>
    <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') : ?>
        <div class="notice notice-success is-dismissible">
            <p>Settings updated successfully.</p>
        </div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="save_typesense_settings">
        <?php wp_nonce_field('typesense_settings_nonce'); ?>
        <table class="form-table">
            <?php foreach ($settings as $key => $setting) : ?>
                <tr>
                    <th scope="row"><?php echo esc_html($setting['label']); ?></th>
                    <td>
                        <?php switch ($setting['type']):
                            case 'checkbox': ?>
                                <input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked(1, $values[$key], true); ?> />
                            <?php break;
                            case 'text':
                            case 'password': ?>
                                <input type="<?php echo esc_attr($setting['type']); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($values[$key]); ?>" />
                        <?php break;
                        // Add more cases for different input types if needed
                        endswitch; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php submit_button('Save Settings'); ?>
    </form>

    <div id="spinner" style="display: none;">
        <img src="<?php echo esc_url(get_admin_url(null, 'images/spinner.gif')); ?>" alt="Loading..." />
    </div>
    
    <button id="test-connection" class="button button-secondary">Test Connection</button>
    <button id="index-products" class="button button-primary">Index Products</button>
    <button id="force-reindex-products" class="button">Force Reindex All Products</button>
    
    <div id="connection-result"></div>
    <div id="indexing-progress"></div>
</div>