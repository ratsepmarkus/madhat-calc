<?php
/**
 * Plugin Name: Madhat Cutlist Calculator
 * Description: Kalkulaator hinnavahemiku, materjali valiku ja CSV ekspordiga. Seadistatav admin paneelist.
 * Version: 1.7
 * Author: Veebmik
 * Author URI: https://veebmik.ee
 * Update URI: https://github.com/ratsepmarkus/madhat-calc
 */

if (!defined('ABSPATH')) {
    exit; 
}

// ---------------------------------------------------------
// 0. AUTOMATIC UPDATER (GitHub)
// ---------------------------------------------------------
$puc_path = plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';

if (file_exists($puc_path)) {
    require_once $puc_path;
    $myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/ratsepmarkus/madhat-calc',
        __FILE__,
        'madhat-calc'
    );
    $myUpdateChecker->setBranch('main');
    $myUpdateChecker->getVcsApi()->enableReleaseAssets();
} else {
    add_action('admin_notices', function() {
        if (current_user_can('activate_plugins')) {
            echo '<div class="notice notice-error"><p><strong>Viga:</strong> Madhat Calculator ei leia kausta "plugin-update-checker".</p></div>';
        }
    });
}

// ---------------------------------------------------------
// 1. ADMIN PANEELI SEADED
// ---------------------------------------------------------

function madhat_register_settings() {
    register_setting('madhat_options_group', 'madhat_recipient_email');
    register_setting('madhat_options_group', 'madhat_price_min');
    register_setting('madhat_options_group', 'madhat_price_max');
}
add_action('admin_init', 'madhat_register_settings');

function madhat_add_admin_menu() {
    add_menu_page(
        'Madhat Kalkulaator', 
        'Madhat Calc', 
        'manage_options', 
        'madhat-settings', 
        'madhat_settings_page_html', 
        'dashicons-calculator', 
        90
    );
}
add_action('admin_menu', 'madhat_add_admin_menu');

function madhat_settings_page_html() {
    ?>
    <div class="wrap">
        <h1>Madhat Kalkulaatori Seaded</h1>
        <form method="post" action="options.php">
            <?php settings_fields('madhat_options_group'); ?>
            <?php do_settings_sections('madhat_options_group'); ?>
            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">E-mail(id) päringuteks:</th>
                    <td>
                        <input type="text" name="madhat_recipient_email" value="<?php echo esc_attr(get_option('madhat_recipient_email')); ?>" class="large-text" placeholder="info@sinufirma.ee, myyk@sinufirma.ee" />
                        <p class="description">Sinna saadetakse kliendi päringud ja CSV fail. Mitme saaja korral eralda aadressid komaga (nt: <code>info@a.ee, mari@a.ee</code>).</p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row">Ruutmeetri hind (€/m²):</th>
                    <td>
                        <label>
                            Min: <input type="number" name="madhat_price_min" value="<?php echo esc_attr(get_option('madhat_price_min', 45)); ?>" class="small-text" step="0.1" /> €
                        </label>
                        &nbsp;&nbsp;&mdash;&nbsp;&nbsp;
                        <label>
                            Max: <input type="number" name="madhat_price_max" value="<?php echo esc_attr(get_option('madhat_price_max', 65)); ?>" class="small-text" step="0.1" /> €
                        </label>
                        <p class="description">Neid numbreid kasutatakse kalkulaatoris hinnavahemiku arvutamiseks (ilma käibemaksuta).</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// ---------------------------------------------------------
// 2. VORMI KUVAMINE
// ---------------------------------------------------------
function madhat_render_form() {
    $price_min = get_option('madhat_price_min', 45);
    $price_max = get_option('madhat_price_max', 65);

    ob_start();
    ?>
    <style>
        /* Modern Reset & Wrapper */
        .madhat-wrapper { 
            max_width: 700px;
            margin: 40px auto; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 30px; 
            background: #ffffff; 
            border-radius: 12px; 
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            color: #374151;
        }
        .madhat-wrapper * { box-sizing: border-box; }

        .madhat-header h3 { 
            margin: 0 0 20px 0; 
            font-size: 1.5rem; 
            font-weight: 700; 
            text-align: center; 
            color: #111827; 
        }

        /* Input Styling */
        .madhat-label { 
            display: block; 
            font-weight: 600; 
            margin-bottom: 6px; 
            font-size: 0.9rem; 
            color: #4b5563; 
        }
        .madhat-input, .madhat-select, .madhat-textarea { 
            width: 100%; 
            padding: 10px 12px; 
            border: 1px solid #d1d5db; 
            border-radius: 6px; 
            font-size: 15px; 
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: #f9fafb;
        }
        .madhat-input:focus, .madhat-select:focus, .madhat-textarea:focus {
            outline: none;
            border-color: #2563eb;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Layout Grid */
        .madhat-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .madhat-mb { margin-bottom: 15px; }

        /* Divider */
        .madhat-divider { height: 1px; background: #e5e7eb; margin: 25px 0; border: none; }

        /* Radio Group */
        .radio-group { 
            display: flex; 
            gap: 15px; 
            background: #f3f4f6; 
            padding: 12px; 
            border-radius: 8px; 
            border: 1px solid #e5e7eb;
        }
        .radio-label { 
            display: flex; 
            align-items: center; 
            cursor: pointer; 
            font-size: 0.95rem; 
            font-weight: 500;
        }
        .radio-label input { margin-right: 8px; transform: scale(1.1); }

        /* Measurements Table Style */
        .measurements-header {
            display: grid;
            grid-template-columns: 2fr 2fr 1fr 3fr 40px;
            gap: 10px;
            margin-bottom: 5px;
        }
        .measurements-header label { font-size: 0.8rem; color: #6b7280; font-weight: 600; }
        
        .item-row {
            display: grid;
            grid-template-columns: 2fr 2fr 1fr 3fr 40px;
            gap: 10px;
            margin-bottom: 10px;
            align-items: start;
        }

        /* Buttons */
        .madhat-btn { 
            display: inline-block;
            padding: 10px 20px; 
            border: none; 
            cursor: pointer; 
            border-radius: 6px; 
            font-size: 15px; 
            font-weight: 600; 
            text-align: center;
            transition: all 0.2s;
        }
        .btn-submit { background-color: #111827; color: #fff; width: 100%; padding: 14px; margin-top: 10px; }
        .btn-submit:hover { background-color: #000; transform: translateY(-1px); }
        
        .btn-add { background-color: #e5e7eb; color: #374151; width: 100%; border: 1px solid #d1d5db; }
        .btn-add:hover { background-color: #d1d5db; }

        .btn-remove { 
            background-color: #fee2e2; 
            color: #ef4444; 
            padding: 0;
            height: 38px; /* Match input height */
            width: 100%;
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border: 1px solid #fecaca;
        }
        .btn-remove:hover { background-color: #fecaca; }

        /* Summary Box */
        .madhat-summary { 
            background: #ecfdf5; 
            border: 1px solid #d1fae5; 
            border-radius: 8px; 
            padding: 20px; 
            margin-top: 25px; 
            text-align: center; 
            color: #065f46;
        }
        .price-range { font-size: 1.8rem; font-weight: 700; display: block; margin: 5px 0; color: #047857; }
        .price-note { font-size: 0.85rem; color: #059669; text-transform: uppercase; letter-spacing: 0.5px; }

        .sub-type-wrap { display: none; margin-bottom: 15px; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        .madhat-alert { padding: 15px; margin-bottom: 20px; background: #d1fae5; color: #065f46; border-radius: 8px; text-align: center; border: 1px solid #a7f3d0; }
        .madhat-honey { display: none !important; }

        /* Mobile Responsive */
        @media (max-width: 500px) {
            .madhat-grid-2 { grid-template-columns: 1fr; }
            .measurements-header { display: none; } /* Hide labels on mobile */
            .item-row { grid-template-columns: 1fr 1fr; gap: 8px; background: #f9fafb; padding: 10px; border-radius: 6px; border: 1px solid #f3f4f6; }
            .item-row .madhat-input { margin-bottom: 5px; }
            /* Force name input to full width on mobile */
            .item-row div:nth-child(4) { grid-column: 1 / -1; } /* Nimetus */
            .item-row button { grid-column: 1 / -1; margin-top: 5px; } /* Delete btn */
            .madhat-wrapper { padding: 20px 15px; }
        }
    </style>

    <div class="madhat-wrapper">
        <?php if (isset($_GET['madhat_sent']) && $_GET['madhat_sent'] == '1'): ?>
            <div class="madhat-alert">
                <strong>Päring edukalt saadetud!</strong><br>
                Oleme kätte saanud ja võtame peagi ühendust.
            </div>
        <?php endif; ?>

        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data" id="madhatForm">
            <input type="hidden" name="action" value="madhat_submit_form">
            <?php wp_nonce_field('madhat_verify', 'madhat_nonce'); ?>
            <input type="text" name="madhat_robot_check" class="madhat-honey" value="">

            <div class="madhat-header"><h3>Hinnapäring & Lõikus</h3></div>

            <div class="madhat-grid-2">
                <div><label class="madhat-label">Nimi</label><input type="text" class="madhat-input" name="client_name" required placeholder="Sinu nimi"></div>
                <div><label class="madhat-label">E-post</label><input type="email" class="madhat-input" name="client_email" required placeholder="näide@email.ee"></div>
            </div>
            <div class="madhat-grid-2">
                <div><label class="madhat-label">Telefon</label><input type="tel" class="madhat-input" name="client_phone" required placeholder="+372 5xxx xxxx"></div>
                <div><label class="madhat-label">Objekti asukoht</label><input type="text" class="madhat-input" name="client_location" placeholder="Tallinn, Pärnu mnt..."></div>
            </div>

            <hr class="madhat-divider">

            <div class="madhat-mb">
                <label class="madhat-label">Vali materjali tüüp:</label>
                <div class="radio-group">
                    <label class="radio-label"><input type="radio" name="material_type" value="aknakile" required onchange="toggleSubType()"> Aknakiled</label>
                    <label class="radio-label"><input type="radio" name="material_type" value="sisustuskile" required onchange="toggleSubType()"> Sisustuskiled</label>
                </div>
            </div>

            <div id="sub-type-container" class="sub-type-wrap">
                <label class="madhat-label">Täpsusta kile tüüp:</label>
                <select name="material_sub_type" class="madhat-select" id="sub_type_select"></select>
            </div>

            <div class="madhat-mb" style="margin-top:25px;">
                <label class="madhat-label" style="display:flex; justify-content:space-between;">
                    Mõõdud (mm)
                    <span style="font-weight:400; font-size:0.8em; color:#9ca3af;">Ümardamine: 1cm täpsuseni</span>
                </label>
                
                <div class="measurements-header">
                    <label>Laius</label>
                    <label>Kõrgus</label>
                    <label>Kogus</label>
                    <label>Nimetus/Info</label>
                    <label></label>
                </div>

                <div id="madhat-rows">
                    <div class="item-row">
                        <div><input type="number" class="madhat-input input-w" name="items[0][w]" placeholder="L (mm)" required onchange="calcPrice(this)"></div>
                        <div><input type="number" class="madhat-input input-h" name="items[0][h]" placeholder="H (mm)" required onchange="calcPrice(this)"></div>
                        <div><input type="number" class="madhat-input input-q" name="items[0][q]" value="1" required onchange="calcPrice()"></div>
                        <div><input type="text" class="madhat-input" name="items[0][l]" placeholder="Nt. Köögiaken"></div>
                        <div></div>
                    </div>
                </div>
                <button type="button" class="madhat-btn btn-add" onclick="addMRow()">+ Lisa uus rida</button>
            </div>

            <div class="madhat-summary" id="price-box" style="display:none;">
                <span>Hinnanguline materjali maksumus:</span>
                <span class="price-range" id="price-display">0€ - 0€</span>
                <span class="price-note">+ KÄIBEMAKS (SISALDAB MATERJALI JA TÖÖD)</span>
            </div>

            <hr class="madhat-divider">

            <div class="madhat-mb">
                <label class="madhat-label">Lisainfo</label>
                <textarea name="client_info" class="madhat-textarea" rows="3" placeholder="Täpsustavad soovid..."></textarea>
            </div>
            
            <div class="madhat-mb">
                <label class="madhat-label">Failid / Pildid / Joonised</label>
                <input type="file" name="client_files[]" multiple style="font-size:0.9em; padding:10px 0;">
            </div>

            <div style="margin-top:30px;">
                <button type="submit" class="madhat-btn btn-submit">SAADA PÄRING</button>
                <p style="text-align:center; font-size:0.85rem; color:#6b7280; margin-top:12px;">Vastame päringule esimesel võimalusel.</p>
            </div>
        </form>
    </div>

    <script>
    // --- SEADISTUSED (Tulevad nüüd PHP-st) ---
    const PRICE_MIN = <?php echo esc_js($price_min); ?>; 
    const PRICE_MAX = <?php echo esc_js($price_max); ?>;
    
    const SUB_TYPES = {
        'aknakile': ['Peegelkile', 'Nanokile', 'Turvakile', 'Toonkile', 'Muu'],
        'sisustuskile': ['Puitimitatsioon', 'Kiviimitatsioon', 'Värviline matt', 'Nahkimitatsioon', 'Muu']
    };

    function toggleSubType() {
        const type = document.querySelector('input[name="material_type"]:checked').value;
        const container = document.getElementById('sub-type-container');
        const select = document.getElementById('sub_type_select');
        
        select.innerHTML = ''; 
        
        if (SUB_TYPES[type]) {
            SUB_TYPES[type].forEach(opt => {
                const option = document.createElement('option');
                option.value = opt;
                option.text = opt;
                select.appendChild(option);
            });
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
        calcPrice();
    }

    function roundToCm(el) {
        if(!el.value) return;
        let val = parseInt(el.value);
        let rounded = Math.round(val / 10) * 10;
        el.value = rounded;
    }

    function calcPrice(el) {
        if (el) roundToCm(el);

        let totalSqM = 0;
        const rows = document.querySelectorAll('.item-row');
        
        rows.forEach(row => {
            const w = row.querySelector('.input-w').value;
            const h = row.querySelector('.input-h').value;
            const q = row.querySelector('.input-q').value;

            if (w && h && q) {
                const area = (w / 1000) * (h / 1000) * q;
                totalSqM += area;
            }
        });

        const box = document.getElementById('price-box');
        const display = document.getElementById('price-display');

        if (totalSqM > 0) {
            const minCost = Math.round(totalSqM * PRICE_MIN);
            const maxCost = Math.round(totalSqM * PRICE_MAX);
            
            display.textContent = `${minCost}€ - ${maxCost}€`;
            box.style.display = 'block';
        } else {
            box.style.display = 'none';
        }
    }

    let rIdx = 1;
    function addMRow() {
        const div = document.createElement('div');
        div.className = 'item-row';
        div.innerHTML = `
            <div><input type="number" class="madhat-input input-w" name="items[${rIdx}][w]" placeholder="L" required onchange="calcPrice(this)"></div>
            <div><input type="number" class="madhat-input input-h" name="items[${rIdx}][h]" placeholder="H" required onchange="calcPrice(this)"></div>
            <div><input type="number" class="madhat-input input-q" name="items[${rIdx}][q]" value="1" required onchange="calcPrice()"></div>
            <div><input type="text" class="madhat-input" name="items[${rIdx}][l]" placeholder="Nimetus"></div>
            <div><button type="button" class="madhat-btn btn-remove" onclick="this.parentElement.parentElement.remove(); calcPrice();">×</button></div>
        `;
        document.getElementById('madhat-rows').appendChild(div);
        rIdx++;
    }
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('madhat_form', 'madhat_render_form');

// ---------------------------------------------------------
// 3. TÖÖTLEMINE JA SAATMINE
// ---------------------------------------------------------
function madhat_handle_submit() {
    if (!isset($_POST['madhat_nonce']) || !wp_verify_nonce($_POST['madhat_nonce'], 'madhat_verify')) wp_die('Turvaviga.');
    if (!empty($_POST['madhat_robot_check'])) wp_die('Spämm.');

    $name = sanitize_text_field($_POST['client_name']);
    $email = sanitize_email($_POST['client_email']);
    $phone = sanitize_text_field($_POST['client_phone']);
    $loc = sanitize_text_field($_POST['client_location']);
    $info = sanitize_textarea_field($_POST['client_info']);
    
    $type = sanitize_text_field($_POST['material_type']);
    $sub_type = sanitize_text_field($_POST['material_sub_type']);
    
    $items = isset($_POST['items']) ? $_POST['items'] : [];
    $offset = ($type === 'sisustuskile') ? 80 : 20;
    
    $csv = "\xEF\xBB\xBFLength,Width,Qty,Label,Enabled\n";
    
    $mail_txt = "UUS PÄRING VEEBILEHELT\n\n";
    $mail_txt .= "Klient: $name\nEmail: $email\nTelefon: $phone\nAsukoht: $loc\n";
    $mail_txt .= "Materjal: $type ($sub_type)\n";
    $mail_txt .= "Lisainfo: $info\n\n";
    $mail_txt .= "SOOVITUD MÕÕDUD (Ümardatud 10mm täpsusega):\n------------------------------------\n";

    if (is_array($items)) {
        foreach ($items as $i) {
            $w_raw = floatval($i['w']);
            $h_raw = floatval($i['h']);
            
            $w = round($w_raw / 10) * 10;
            $h = round($h_raw / 10) * 10;
            
            $q = intval($i['q']);
            $l = sanitize_text_field($i['l']);

            if ($w <= 0 || $h <= 0 || $q <= 0) continue;

            $mail_txt .= "- $l: $w x $h mm ($q tk)\n";

            $csv_w = $w + $offset;
            $csv_h = $h + $offset;
            $l_csv = str_replace('"', '""', $l); 
            $csv .= "$csv_h,$csv_w,$q,\"$l_csv\",true\n";
        }
    }

    $attachments = [];
    $upload = wp_upload_dir();
    $filename = 'cutlist_' . date('ymd_Hi') . '_' . sanitize_file_name($name) . '.csv';
    $csv_path = $upload['path'] . '/' . $filename;
    
    $f = fopen($csv_path, 'w');
    if ($f) { fwrite($f, $csv); fclose($f); $attachments[] = $csv_path; }

    if (!empty($_FILES['client_files']['name'][0])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        foreach ($_FILES['client_files']['name'] as $k => $v) {
            if ($_FILES['client_files']['name'][$k]) {
                $file = [
                    'name' => sanitize_file_name($_FILES['client_files']['name'][$k]),
                    'type' => $_FILES['client_files']['type'][$k],
                    'tmp_name' => $_FILES['client_files']['tmp_name'][$k],
                    'error' => $_FILES['client_files']['error'][$k],
                    'size' => $_FILES['client_files']['size'][$k]
                ];
                $up = wp_handle_upload($file, ['test_form' => false]);
                if ($up && !isset($up['error'])) $attachments[] = $up['file'];
            }
        }
    }

    // --- E-MAILI LOOGIKA ---
    // Saame kirjutada: info@mail.ee, teine@mail.ee
    // WordPressi wp_mail() saab ise komadega eraldatud listist aru.
    $recipient = get_option('madhat_recipient_email', get_option('admin_email'));
    
    $headers = ['Content-Type: text/plain; charset=UTF-8', 'From: Madhat Kalkulaator <wordpress@' . $_SERVER['SERVER_NAME'] . '>'];
    
    wp_mail($recipient, "Päring: $name ($type)", $mail_txt, $headers, $attachments);

    wp_redirect(add_query_arg('madhat_sent', '1', wp_get_referer()));
    exit;
}
add_action('admin_post_madhat_submit_form', 'madhat_handle_submit');
add_action('admin_post_nopriv_madhat_submit_form', 'madhat_handle_submit');