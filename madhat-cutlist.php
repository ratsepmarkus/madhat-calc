<?php
/**
 * Plugin Name: Madhat Cutlist Calculator
 * Description: Kalkulaator hinnavahemiku, materjali valiku ja CSV ekspordiga. Seadistatav admin paneelist.
 * Version: 1.13
 * Author: Veebmik
 * Author URI: https://veebmik.ee
 * Update URI: https://github.com/ratsepmarkus/madhat-calc
 */

if (!defined('ABSPATH')) {
    exit; 
}

// ---------------------------------------------------------
// 0. AUTOMATIC UPDATER
// ---------------------------------------------------------
$puc_path = plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
if (file_exists($puc_path)) {
    require_once $puc_path;
    $myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/ratsepmarkus/madhat-calc', __FILE__, 'madhat-calc'
    );
    $myUpdateChecker->setBranch('main');
    $myUpdateChecker->getVcsApi()->enableReleaseAssets();
}

// ---------------------------------------------------------
// 1. ADMIN PANEELI SEADED
// ---------------------------------------------------------

function madhat_register_settings() {
    register_setting('madhat_options_group', 'madhat_recipient_email');
    register_setting('madhat_options_group', 'madhat_price_min');
    register_setting('madhat_options_group', 'madhat_price_max');
    register_setting('madhat_options_group', 'madhat_opts_window');
    register_setting('madhat_options_group', 'madhat_opts_interior');
}
add_action('admin_init', 'madhat_register_settings');

function madhat_add_admin_menu() {
    add_menu_page('Madhat Kalkulaator', 'Madhat Calc', 'manage_options', 'madhat-settings', 'madhat_settings_page_html', 'dashicons-calculator', 90);
}
add_action('admin_menu', 'madhat_add_admin_menu');

function madhat_settings_page_html() {
    $def_win = "3M Prestige nanokile\nDekoratiiv/Mattkile\nTurvakile\nMuu";
    $def_int = "Puitimitatsioon\nKiviimitatsioon\nVärviline matt\nNahkimitatsioon\nMuu";
    ?>
    <div class="wrap">
        <h1>Madhat Kalkulaatori Seaded</h1>
        <form method="post" action="options.php">
            <?php settings_fields('madhat_options_group'); ?>
            <?php do_settings_sections('madhat_options_group'); ?>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div>
                    <h3>Üldseaded</h3>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">E-mail(id):</th>
                            <td>
                                <input type="text" name="madhat_recipient_email" value="<?php echo esc_attr(get_option('madhat_recipient_email')); ?>" class="large-text" />
                                <p class="description">Eralda komaga.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Hind (€/m²):</th>
                            <td>
                                <label>Min: <input type="number" name="madhat_price_min" value="<?php echo esc_attr(get_option('madhat_price_min', 45)); ?>" class="small-text" step="0.1" /> €</label><br>
                                <label>Max: <input type="number" name="madhat_price_max" value="<?php echo esc_attr(get_option('madhat_price_max', 65)); ?>" class="small-text" step="0.1" /> €</label>
                            </td>
                        </tr>
                    </table>
                </div>
                <div>
                    <h3>Materjalide valikud</h3>
                    <p class="description">Kirjuta iga valik <strong>uuele reale</strong>.</p>
                    <label><strong>Aknakiled (Automaatne offset +20mm):</strong></label><br>
                    <textarea name="madhat_opts_window" rows="5" class="large-text code"><?php echo esc_textarea(get_option('madhat_opts_window', $def_win)); ?></textarea>
                    <br><br>
                    <label><strong>Sisustuskiled (Automaatne offset +80mm):</strong></label><br>
                    <textarea name="madhat_opts_interior" rows="5" class="large-text code"><?php echo esc_textarea(get_option('madhat_opts_interior', $def_int)); ?></textarea>
                </div>
            </div>
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

    $raw_win = get_option('madhat_opts_window');
    $raw_int = get_option('madhat_opts_interior');

    if (empty(trim($raw_win))) $arr_win = ['3M Prestige nanokile', 'Dekoratiiv/Mattkile', 'Turvakile', 'Muu'];
    else $arr_win = array_filter(array_map('trim', explode("\n", $raw_win)));

    if (empty(trim($raw_int))) $arr_int = ['Puitimitatsioon', 'Kiviimitatsioon', 'Värviline matt', 'Nahkimitatsioon', 'Muu'];
    else $arr_int = array_filter(array_map('trim', explode("\n", $raw_int)));

    $json_win = json_encode(array_values($arr_win));
    $json_int = json_encode(array_values($arr_int));

    ob_start();
    ?>
    <style>
        .madhat-wrapper { 
            max_width: 800px; margin: 40px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 30px; background: #ffffff; border-radius: 12px; 
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1); color: #374151;
        }
        .madhat-wrapper * { box-sizing: border-box; }
        .madhat-header h3 { margin: 0 0 20px 0; font-size: 1.5rem; font-weight: 700; text-align: center; color: #111827; }

        .madhat-label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.9rem; color: #4b5563; }
        .madhat-input, .madhat-select, .madhat-textarea { 
            width: 100%; padding: 8px 10px; /* VÄIKSEM PADDING DESKTOPIL */
            border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px; background-color: #f9fafb;
        }
        .madhat-input:focus, .madhat-select:focus { outline: none; border-color: #2563eb; background-color: #fff; }

        .madhat-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .madhat-mb { margin-bottom: 15px; }
        .madhat-divider { height: 1px; background: #e5e7eb; margin: 25px 0; border: none; }

        /* ITEM CARD STYLE V2.2 */
        .item-row {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 15px;
            padding-top: 25px; 
            margin-bottom: 10px;
            position: relative;
        }
        
        .row-number {
            position: absolute;
            top: 5px;
            left: 10px;
            font-size: 0.7rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
        }

        /* --- DESKTOP LAYOUT (> 1200px) --- */
        .measurements-header {
            display: grid;
            /* Mat, Name, W, H, Q, Del */
            grid-template-columns: 2fr 1.5fr 1fr 1fr 0.6fr 40px; 
            gap: 10px; margin-bottom: 5px;
        }
        .measurements-header label { font-size: 0.8rem; color: #6b7280; font-weight: 600; }

        .item-grid {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1fr 1fr 0.6fr 40px; 
            gap: 10px;
            align-items: center;
        }

        /* Buttons */
        .madhat-btn { padding: 10px 20px; border: none; cursor: pointer; border-radius: 6px; font-size: 15px; font-weight: 600; transition: all 0.2s; }
        .btn-submit { 
            background-color: #111827; color: #fff; padding: 14px 40px; margin-top: 10px; 
            display: block; margin-left: auto; margin-right: auto;
        }
        .btn-submit:hover { background-color: #000; }
        .btn-add { background-color: #e5e7eb; color: #374151; width: 100%; border: 1px solid #d1d5db; padding: 12px; }
        .btn-add:hover { background-color: #d1d5db; }
        
        /* TRASH ICON BUTTON */
        .btn-remove { 
            background-color: #fee2e2; color: #ef4444; height: 38px; width: 100%; 
            display: flex; align-items: center; justify-content: center; border: 1px solid #fecaca; padding: 0;
            border-radius: 6px;
        }
        .btn-remove svg { width: 18px; height: 18px; fill: currentColor; }
        .btn-remove:hover { background-color: #fecaca; }

        .madhat-summary { background: #ecfdf5; border: 1px solid #d1fae5; border-radius: 8px; padding: 20px; margin-top: 25px; text-align: center; color: #065f46; }
        .price-range { font-size: 1.8rem; font-weight: 700; display: block; margin: 5px 0; color: #047857; }
        
        .confirm-wrap { text-align: center; margin-top: 20px; }
        .confirm-box { display: inline-flex; align-items: center; justify-content: center; gap: 10px; background: #fffbeb; padding: 10px 20px; border-radius: 6px; border: 1px solid #fcd34d; font-size: 0.95rem; }
        .confirm-box input { transform: scale(1.3); cursor: pointer; }

        .madhat-alert { padding: 15px; margin-bottom: 20px; background: #d1fae5; color: #065f46; border-radius: 8px; text-align: center; border: 1px solid #a7f3d0; }
        .madhat-honey { display: none !important; }
        
        /* Mobile Labels (Hidden on Desktop) */
        .mobile-label { display: none; font-size: 0.75rem; color: #64748b; font-weight: 600; margin-bottom: 3px; }

        /* --- TABLET & MOBILE LAYOUT (< 1200px) --- */
        @media (max-width: 1200px) {
            .madhat-grid-2 { grid-template-columns: 1fr; }
            .measurements-header { display: none; } /* Hide table header */
            .mobile-label { display: block; } /* Show labels above inputs */
            
            .item-row { padding-top: 30px; }
            
            .item-grid {
                /* Row 2 layout: Width | Height | Qty | Delete */
                grid-template-columns: 1fr 1fr 0.8fr 40px;
                grid-template-areas: 
                    "mat mat name name" 
                    "width height qty del"; 
                gap: 10px; 
            }
            
            .grid-mat { grid-area: mat; }
            .grid-n { grid-area: name; }
            .grid-w { grid-area: width; }
            .grid-h { grid-area: height; }
            .grid-q { grid-area: qty; }
            .grid-d { grid-area: del; }
            
            .madhat-wrapper { padding: 20px 15px; }
            
            /* Align delete button to bottom of inputs */
            .btn-remove { margin-top: 17px; height: 38px; }
        }
    </style>

    <div class="madhat-wrapper">
        <?php if (isset($_GET['madhat_sent']) && $_GET['madhat_sent'] == '1'): ?>
            <div class="madhat-alert">
                <strong>Päring edukalt saadetud!</strong><br>
                Oleme kätte saanud ja võtame peagi ühendust.
            </div>
            <script>localStorage.removeItem('madhat_form_data_v2');</script>
        <?php endif; ?>

        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data" id="madhatForm">
            <input type="hidden" name="action" value="madhat_submit_form">
            <?php wp_nonce_field('madhat_verify', 'madhat_nonce'); ?>
            <input type="text" name="madhat_robot_check" class="madhat-honey" value="">

            <div class="madhat-header"><h3>Hinnapäring</h3></div>

            <div class="madhat-grid-2">
                <div><label class="madhat-label">Projekti pealkiri</label><input type="text" class="madhat-input" name="project_title" id="project_title" required placeholder="nt. Korteri sisustus"></div>
                <div><label class="madhat-label">Nimi (Kontaktisik)</label><input type="text" class="madhat-input" name="contact_name" id="contact_name" required placeholder="Sinu nimi"></div>
            </div>
            <div class="madhat-grid-2">
                <div><label class="madhat-label">E-post</label><input type="email" class="madhat-input" name="client_email" id="client_email" required placeholder="näide@email.ee"></div>
                <div><label class="madhat-label">Telefon</label><input type="tel" class="madhat-input" name="client_phone" id="client_phone" required placeholder="+372..."></div>
            </div>

            <hr class="madhat-divider">

            <div class="madhat-mb" style="margin-top:25px;">
                <label class="madhat-label">Materjalid ja Mõõdud (mm)</label>
                
                <div class="measurements-header">
                    <label>Materjal</label>
                    <label>Pinna nimetus</label>
                    <label>Laius</label>
                    <label>Kõrgus</label>
                    <label>Kogus</label>
                    <label></label>
                </div>

                <div id="madhat-rows"></div>
                <button type="button" class="madhat-btn btn-add" onclick="addMRow(true)">+ Lisa uus rida</button>
            </div>

            <div class="madhat-summary" id="price-box" style="display:none;">
                <span>Hinnanguline projekti kogumaksumus:</span>
                <span class="price-range" id="price-display">€0 - 0 + KM</span>
            </div>

            <hr class="madhat-divider">

            <div class="madhat-mb">
                <label class="madhat-label">Lisainfo</label>
                <textarea name="client_info" id="client_info" class="madhat-textarea" rows="3" placeholder="Täpsustavad soovid / objekti asukoht / tellingu vajadus..." oninput="saveState()"></textarea>
            </div>
            
            <div class="madhat-mb">
                <label class="madhat-label">Failid / Pildid / Joonised</label>
                <input type="file" name="client_files[]" multiple style="font-size:0.9em; padding:10px 0;">
            </div>

            <div class="confirm-wrap">
                <div class="confirm-box">
                    <input type="checkbox" name="confirm_measurements" required>
                    <label>Kinnitan mõõtude täpsuse +/- 1cm</label>
                </div>
            </div>

            <div style="margin-top:20px;">
                <button type="submit" class="madhat-btn btn-submit">SAADA PÄRING</button>
            </div>
        </form>
    </div>

    <script>
    const PRICE_MIN = <?php echo esc_js($price_min); ?>; 
    const PRICE_MAX = <?php echo esc_js($price_max); ?>;
    
    const WIN_OPTS = <?php echo $json_win; ?>;
    const INT_OPTS = <?php echo $json_int; ?>;

    function buildOptions(selected = '') {
        let html = '<option value="" disabled selected>-- Vali materjal --</option>';
        html += '<optgroup label="Aknakiled">';
        WIN_OPTS.forEach(opt => {
            const val = 'win|' + opt;
            const isSel = (val === selected) ? 'selected' : '';
            html += `<option value="${val}" ${isSel}>${opt}</option>`;
        });
        html += '</optgroup>';
        html += '<optgroup label="Sisustuskiled">';
        INT_OPTS.forEach(opt => {
            const val = 'int|' + opt;
            const isSel = (val === selected) ? 'selected' : '';
            html += `<option value="${val}" ${isSel}>${opt}</option>`;
        });
        html += '</optgroup>';
        return html;
    }

    function saveState() {
        const formData = {
            project_title: document.getElementById('project_title').value,
            contact_name: document.getElementById('contact_name').value,
            email: document.getElementById('client_email').value,
            phone: document.getElementById('client_phone').value,
            info: document.getElementById('client_info').value,
            items: []
        };
        document.querySelectorAll('.item-row').forEach(row => {
            formData.items.push({
                mat: row.querySelector('.input-mat').value,
                w: row.querySelector('.input-w').value,
                h: row.querySelector('.input-h').value,
                q: row.querySelector('.input-q').value,
                l: row.querySelector('.input-l').value
            });
        });
        localStorage.setItem('madhat_form_data_v2', JSON.stringify(formData));
        calcPrice();
    }

    function restoreState() {
        const saved = localStorage.getItem('madhat_form_data_v2');
        if (!saved) { addMRow(); return; }
        const data = JSON.parse(saved);

        if(data.project_title) document.getElementById('project_title').value = data.project_title;
        if(data.contact_name) document.getElementById('contact_name').value = data.contact_name;
        if(data.email) document.getElementById('client_email').value = data.email;
        if(data.phone) document.getElementById('client_phone').value = data.phone;
        if(data.info) document.getElementById('client_info').value = data.info;

        const container = document.getElementById('madhat-rows');
        container.innerHTML = '';

        if (data.items && data.items.length > 0) {
            data.items.forEach((item) => addMRow(false, item));
        } else {
            addMRow();
        }
        calcPrice();
    }

    ['project_title', 'contact_name', 'client_email', 'client_phone'].forEach(id => {
        document.getElementById(id).addEventListener('input', saveState);
    });

    function getRoundedUpVal(val) {
        if(!val) return 0;
        let v = parseInt(val);
        return Math.ceil(v / 10) * 10;
    }

    function calcPrice() {
        let totalSqM = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const wRaw = row.querySelector('.input-w').value;
            const hRaw = row.querySelector('.input-h').value;
            const q = row.querySelector('.input-q').value;
            const w = getRoundedUpVal(wRaw);
            const h = getRoundedUpVal(hRaw);
            if (w && h && q) {
                totalSqM += (w / 1000) * (h / 1000) * q;
            }
        });
        const box = document.getElementById('price-box');
        const display = document.getElementById('price-display');
        if (totalSqM > 0) {
            const minCost = Math.round(totalSqM * PRICE_MIN);
            const maxCost = Math.round(totalSqM * PRICE_MAX);
            display.textContent = `€${minCost} - ${maxCost} + KM`;
            box.style.display = 'block';
        } else {
            box.style.display = 'none';
        }
    }

    function updateRowNumbers() {
        document.querySelectorAll('.item-row').forEach((row, index) => {
            const numEl = row.querySelector('.row-number');
            if(numEl) numEl.textContent = 'Nr. ' + (index + 1);
        });
    }

    let rIdx = 0;
    function addMRow(shouldSave = true, values = null) {
        const div = document.createElement('div');
        div.className = 'item-row';
        
        const mat = values ? values.mat : '';
        const w = values ? values.w : '';
        const h = values ? values.h : '';
        const q = values ? values.q : '1';
        const l = values ? values.l : '';
        
        const optionsHtml = buildOptions(mat);

        // Trash Icon SVG
        const trashSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C12.5523 2 13 2.44772 13 3V4H19C19.5523 4 20 4.44772 20 5C20 5.55228 19.5523 6 19 6H5C4.44772 6 4 5.55228 4 5C4 4.44772 4.44772 4 5 4H11V3C11 2.44772 11.4477 2 12 2ZM6 8V20C6 21.1046 6.89543 22 8 22H16C17.1046 22 18 21.1046 18 20V8H6ZM9 10C9.55228 10 10 10.4477 10 11V19C10 19.5523 9.55228 20 9 20C8.44772 20 8 19.5523 8 19V11C8 10.4477 8.44772 10 9 10ZM15 10C15.5523 10 16 10.4477 16 11V19C16 19.5523 15.5523 20 15 20C14.4477 20 14 19.5523 14 19V11C14 10.4477 14.4477 10 15 10Z"></path></svg>`;

        div.innerHTML = `
            <span class="row-number"></span>
            <div class="item-grid">
                <div class="grid-mat">
                    <span class="mobile-label">Materjal</span>
                    <select class="madhat-select input-mat" name="items[${rIdx}][mat]" required onchange="saveState()">
                        ${optionsHtml}
                    </select>
                </div>
                <div class="grid-n">
                    <span class="mobile-label">Pinna nimetus</span>
                    <input type="text" class="madhat-input input-l" name="items[${rIdx}][l]" value="${l}" placeholder="nt. Köögi aken" oninput="saveState()">
                </div>
                
                <div class="grid-w">
                    <span class="mobile-label">Laius (mm)</span>
                    <input type="number" class="madhat-input input-w" name="items[${rIdx}][w]" value="${w}" placeholder="Laius (mm)" required onchange="calcPrice(); saveState()">
                </div>
                <div class="grid-h">
                    <span class="mobile-label">Kõrgus (mm)</span>
                    <input type="number" class="madhat-input input-h" name="items[${rIdx}][h]" value="${h}" placeholder="Kõrgus (mm)" required onchange="calcPrice(); saveState()">
                </div>
                <div class="grid-q">
                    <span class="mobile-label">Kogus</span>
                    <input type="number" class="madhat-input input-q" name="items[${rIdx}][q]" value="${q}" required onchange="calcPrice(); saveState()">
                </div>
                <div class="grid-d">
                    <button type="button" class="madhat-btn btn-remove" onclick="this.closest('.item-row').remove(); updateRowNumbers(); calcPrice(); saveState();">${trashSvg}</button>
                </div>
            </div>
        `;
        document.getElementById('madhat-rows').appendChild(div);
        rIdx++;
        updateRowNumbers();
        if(shouldSave) saveState();
    }

    document.addEventListener('DOMContentLoaded', restoreState);
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

    $project = sanitize_text_field($_POST['project_title']);
    $contact = sanitize_text_field($_POST['contact_name']);
    $email = sanitize_email($_POST['client_email']);
    $phone = sanitize_text_field($_POST['client_phone']);
    $info = sanitize_textarea_field($_POST['client_info']);
    
    $items = isset($_POST['items']) ? $_POST['items'] : [];
    
    $csv = "\xEF\xBB\xBFLength,Width,Qty,Label,Enabled\n";
    $mail_txt = "UUS PÄRING VEEBILEHELT\n\nPROJEKT: $project\nKontaktisik: $contact\nEmail: $email\nTelefon: $phone\nLisainfo: $info\n\nMATERJALID JA MÕÕDUD:\n------------------------------------\n";

    if (is_array($items)) {
        foreach ($items as $i) {
            $mat_raw = sanitize_text_field($i['mat']); 
            $parts = explode('|', $mat_raw);
            $mat_type = isset($parts[0]) ? $parts[0] : 'win'; 
            $mat_name = isset($parts[1]) ? $parts[1] : 'Määramata';
            $offset = ($mat_type === 'int') ? 80 : 20;

            $w_raw = floatval($i['w']);
            $h_raw = floatval($i['h']);
            $w = ceil($w_raw / 10) * 10;
            $h = ceil($h_raw / 10) * 10;
            $q = intval($i['q']);
            $l = sanitize_text_field($i['l']);

            if ($w <= 0 || $h <= 0 || $q <= 0) continue;

            $mail_txt .= "- $mat_name: $w x $h mm ($q tk) - $l\n";
            $csv_w = $w + $offset;
            $csv_h = $h + $offset;
            $full_label = $l . ' (' . $mat_name . ')';
            $l_csv = str_replace('"', '""', $full_label); 
            $csv .= "$csv_h,$csv_w,$q,\"$l_csv\",true\n";
        }
    }

    $attachments = [];
    $upload = wp_upload_dir();
    $filename = 'cutlist_' . date('ymd_Hi') . '_' . sanitize_file_name($project) . '.csv';
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

    $recipient = get_option('madhat_recipient_email', get_option('admin_email'));
    $headers = ['Content-Type: text/plain; charset=UTF-8', 'From: ' . $contact . ' <wordpress@' . $_SERVER['SERVER_NAME'] . '>', 'Reply-To: ' . $email];
    
    wp_mail($recipient, $project, $mail_txt, $headers, $attachments);

    wp_redirect(add_query_arg('madhat_sent', '1', wp_get_referer()));
    exit;
}
add_action('admin_post_madhat_submit_form', 'madhat_handle_submit');
add_action('admin_post_nopriv_madhat_submit_form', 'madhat_handle_submit');