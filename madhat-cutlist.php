<?php
/**
 * Plugin Name: Madhat Cutlist Calculator
 * Description: Kalkulaator hinnavahemiku, materjali valiku ja CSV ekspordiga.
 * Version: 2.7.3
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
    register_setting('madhat_options_group', 'madhat_wastage_percent');
    register_setting('madhat_options_group', 'madhat_json_window');
    register_setting('madhat_options_group', 'madhat_json_interior');
}
add_action('admin_init', 'madhat_register_settings');

function madhat_add_admin_menu() {
    add_menu_page('Madhat Kalkulaator', 'Madhat Calc', 'manage_options', 'madhat-settings', 'madhat_settings_page_html', 'dashicons-calculator', 90);
}
add_action('admin_menu', 'madhat_add_admin_menu');

function madhat_settings_page_html() {
    $def_win = '[{"name":"3M Prestige nanokile","min":60,"max":80},{"name":"Dekoratiiv/Mattkile","min":30,"max":45},{"name":"Turvakile","min":40,"max":60}]';
    $def_int = '[{"name":"Puitimitatsioon","min":50,"max":70},{"name":"Kiviimitatsioon","min":50,"max":70},{"name":"Värviline matt","min":35,"max":50}]';

    $val_win = get_option('madhat_json_window', $def_win);
    $val_int = get_option('madhat_json_interior', $def_int);
    
    if(empty($val_win)) $val_win = $def_win;
    if(empty($val_int)) $val_int = $def_int;
    ?>
    <div class="wrap">
        <h1>Madhat Kalkulaatori Seaded</h1>
        
        <div style="background:#e3f2fd; border-left:4px solid #2196f3; padding:15px; margin-bottom:20px; max-width:800px;">
            <h3 style="margin-top:0;">Kuidas kasutada?</h3>
            <p>Vormi kuvamiseks lisa soovitud lehele lühikood:</p>
            <code style="font-size:1.2em; display:block; margin:10px 0;">[madhat_form]</code>
            <p>Vorm kohandub automaatselt mobiili ja arvuti vaatega.</p>
        </div>

        <form method="post" action="options.php" id="madhatAdminForm">
            <?php settings_fields('madhat_options_group'); ?>
            <?php do_settings_sections('madhat_options_group'); ?>
            
            <input type="hidden" name="madhat_json_window" id="input_json_window" value="<?php echo esc_attr($val_win); ?>">
            <input type="hidden" name="madhat_json_interior" id="input_json_interior" value="<?php echo esc_attr($val_int); ?>">

            <div style="display:grid; grid-template-columns: 1fr; gap:20px; max_width: 800px;">
                
                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:4px;">
                    <h3>Üldseaded</h3>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">E-mail(id):</th>
                            <td>
                                <input type="text" name="madhat_recipient_email" value="<?php echo esc_attr(get_option('madhat_recipient_email')); ?>" class="large-text" placeholder="info@sinufirma.ee" />
                                <p class="description">Siia saadetakse päringud.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Materjali kadu (%):</th>
                            <td>
                                <input type="number" name="madhat_wastage_percent" value="<?php echo esc_attr(get_option('madhat_wastage_percent', 10)); ?>" class="small-text" step="1" /> %
                                <p class="description">Lisatakse lõpphinnale (buffer).</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:4px;">
                    <h3>Aknakiled (Automaatne offset +20mm)</h3>
                    <table class="wp-list-table widefat fixed striped" id="table-win">
                        <thead>
                            <tr>
                                <th>Nimetus</th>
                                <th style="width:100px;">Min €/m²</th>
                                <th style="width:100px;">Max €/m²</th>
                                <th style="width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <button type="button" class="button action" onclick="addAdminRow('table-win')">+ Lisa rida</button>
                </div>

                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:4px;">
                    <h3>Sisustuskiled (Automaatne offset +80mm)</h3>
                    <table class="wp-list-table widefat fixed striped" id="table-int">
                        <thead>
                            <tr>
                                <th>Nimetus</th>
                                <th style="width:100px;">Min €/m²</th>
                                <th style="width:100px;">Max €/m²</th>
                                <th style="width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <button type="button" class="button action" onclick="addAdminRow('table-int')">+ Lisa rida</button>
                </div>

            </div>
            <br><?php submit_button(); ?>
        </form>
    </div>

    <script>
    const dataWin = <?php echo $val_win; ?>;
    const dataInt = <?php echo $val_int; ?>;

    function renderTable(tableId, data) {
        const tbody = document.querySelector(`#${tableId} tbody`);
        tbody.innerHTML = '';
        data.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" class="regular-text" style="width:100%" value="${row.name}" oninput="updateJson('${tableId}')"></td>
                <td><input type="number" step="0.1" style="width:100%" value="${row.min}" oninput="updateJson('${tableId}')"></td>
                <td><input type="number" step="0.1" style="width:100%" value="${row.max}" oninput="updateJson('${tableId}')"></td>
                <td><button type="button" class="button" onclick="removeRow('${tableId}', ${index})" style="color:#b32d2e;">&times;</button></td>
            `;
            tbody.appendChild(tr);
        });
    }

    function addAdminRow(tableId) {
        const tbody = document.querySelector(`#${tableId} tbody`);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" class="regular-text" style="width:100%" placeholder="Nimi" oninput="updateJson('${tableId}')"></td>
            <td><input type="number" step="0.1" style="width:100%" value="0" oninput="updateJson('${tableId}')"></td>
            <td><input type="number" step="0.1" style="width:100%" value="0" oninput="updateJson('${tableId}')"></td>
            <td><button type="button" class="button" onclick="this.closest('tr').remove(); updateJson('${tableId}');" style="color:#b32d2e;">&times;</button></td>
        `;
        tbody.appendChild(tr);
        updateJson(tableId);
    }

    function removeRow(tableId, index) {
        const currentData = scrapeData(tableId);
        currentData.splice(index, 1);
        renderTable(tableId, currentData);
        saveToInput(tableId, currentData);
    }

    function scrapeData(tableId) {
        const rows = document.querySelectorAll(`#${tableId} tbody tr`);
        const data = [];
        rows.forEach(tr => {
            const inputs = tr.querySelectorAll('input');
            if(inputs.length > 0) {
                data.push({ name: inputs[0].value, min: inputs[1].value, max: inputs[2].value });
            }
        });
        return data;
    }

    function saveToInput(tableId, data) {
        if(tableId === 'table-win') document.getElementById('input_json_window').value = JSON.stringify(data);
        else document.getElementById('input_json_interior').value = JSON.stringify(data);
    }

    function updateJson(tableId) { saveToInput(tableId, scrapeData(tableId)); }

    document.addEventListener('DOMContentLoaded', () => {
        renderTable('table-win', dataWin);
        renderTable('table-int', dataInt);
    });
    </script>
    <?php
}

// ---------------------------------------------------------
// 2. VORMI KUVAMINE
// ---------------------------------------------------------
function madhat_render_form() {
    $wastage = get_option('madhat_wastage_percent', 10);
    $json_win = get_option('madhat_json_window');
    $json_int = get_option('madhat_json_interior');
    
    if(empty($json_win)) $json_win = '[]';
    if(empty($json_int)) $json_int = '[]';

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
            width: 100%; padding: 8px 10px;
            border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px; background-color: #f9fafb;
        }
        .madhat-input:focus, .madhat-select:focus { outline: none; border-color: #2563eb; background-color: #fff; }

        .madhat-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .madhat-mb { margin-bottom: 15px; }
        .madhat-divider { height: 1px; background: #e5e7eb; margin: 25px 0; border: none; }

        .item-row {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 10px 15px; padding-top: 25px; margin-bottom: 10px; position: relative;
        }
        
        .row-number {
            position: absolute; top: 5px; left: 10px;
            font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase;
        }

        /* Desktop Header */
        .measurements-header {
            display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr 0.6fr 40px; 
            gap: 10px; margin-bottom: 5px;
        }
        .measurements-header label { font-size: 0.8rem; color: #6b7280; font-weight: 600; }

        .item-grid {
            display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr 0.6fr 40px; 
            gap: 10px; align-items: center;
        }

        .madhat-btn { padding: 10px 20px; border: none; cursor: pointer; border-radius: 6px; font-size: 15px; font-weight: 600; transition: all 0.2s; }
        
        /* Submit button */
        .btn-submit { background-color: #111827; color: #fff; padding: 14px 25px; display: inline-block; }
        .btn-submit:hover { background-color: #000; }
        
        .btn-add { background-color: #e5e7eb; color: #374151; width: 100%; border: 1px solid #d1d5db; padding: 12px; }
        .btn-add:hover { background-color: #d1d5db; }
        
        .btn-remove { 
            background-color: #fee2e2; color: #ef4444; height: 38px; width: 100%; 
            display: flex; align-items: center; justify-content: center; border: 1px solid #fecaca; padding: 0;
            border-radius: 6px;
        }
        .btn-remove svg { width: 18px; height: 18px; fill: currentColor; }
        .btn-remove:hover { background-color: #fecaca; }

        .madhat-summary { background: #ecfdf5; border: 1px solid #d1fae5; border-radius: 8px; padding: 20px; margin-top: 25px; text-align: center; color: #065f46; }
        .price-range { font-size: 1.8rem; font-weight: 700; display: block; margin: 5px 0; color: #047857; }

        /* Confirm & Submit Area (Desktop: Row) */
        .submit-area {
            margin-top: 20px; display: flex; align-items: center; justify-content: flex-start; gap: 20px;
        }
        .confirm-box { 
            display: flex; align-items: center; gap: 8px; font-size: 0.95rem; cursor: pointer;
        }
        .confirm-box input { transform: scale(1.2); cursor: pointer; }
        .confirm-error { color: #dc2626; animation: shake 0.4s; }
        @keyframes shake { 0% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } 100% { transform: translateX(0); } }

        .madhat-alert { padding: 15px; margin-bottom: 20px; background: #d1fae5; color: #065f46; border-radius: 8px; text-align: center; border: 1px solid #a7f3d0; }
        .madhat-honey { display: none !important; }
        
        .mobile-label { display: none; font-size: 0.75rem; color: #64748b; font-weight: 600; margin-bottom: 3px; }

        /* --- TABLET & MOBILE LAYOUT (< 1200px) --- */
        @media (max-width: 1200px) {
            .madhat-grid-2 { grid-template-columns: 1fr; }
            .measurements-header { display: none; } 
            
            .item-row.first-row .mobile-label { display: block; min-height: 15px; } 
            .item-row:not(.first-row) .mobile-label { display: none; }
            
            .item-row { padding-top: 30px; }
            
            .madhat-input, .madhat-select {
                padding: 6px 4px !important;
                font-size: 12px !important;
            }

            .item-grid {
                grid-template-columns: 1fr 1fr 0.8fr 40px;
                grid-template-areas: 
                    "mat mat name name" 
                    "width height qty del"; 
                gap: 6px; 
            }
            .grid-mat { grid-area: mat; }
            .grid-n { grid-area: name; }
            .grid-w { grid-area: width; }
            .grid-h { grid-area: height; }
            .grid-q { grid-area: qty; }
            .grid-d { grid-area: del; align-items: flex-end; display: flex; flex-direction: column; }
            
            .madhat-wrapper { padding: 20px 15px; }
            .btn-remove { margin-top: auto; height: 35px; }

            .submit-area {
                flex-direction: column-reverse; 
                align-items: center;
                gap: 20px;
            }
            .btn-submit { width: 100%; text-align: center; }
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

        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data" id="madhatForm" onsubmit="return validateForm()">
            <input type="hidden" name="action" value="madhat_submit_form">
            <?php wp_nonce_field('madhat_verify', 'madhat_nonce'); ?>
            <input type="text" name="madhat_robot_check" class="madhat-honey" value="">

            <div class="madhat-header"><h3>Hinnapäring</h3></div>

            <div class="madhat-grid-2">
                <div>
                    <label class="madhat-label">Teema</label>
                    <input type="text" class="madhat-input" name="project_title" id="project_title" required placeholder="nt. Korteri sisustus" 
                           oninvalid="this.setCustomValidity('Palun täida see väli')" oninput="this.setCustomValidity('')">
                </div>
                <div>
                    <label class="madhat-label">Nimi (Kontaktisik)</label>
                    <input type="text" class="madhat-input" name="contact_name" id="contact_name" required placeholder="Sinu nimi" 
                           oninvalid="this.setCustomValidity('Palun täida see väli')" oninput="this.setCustomValidity('')">
                </div>
            </div>
            <div class="madhat-grid-2">
                <div>
                    <label class="madhat-label">E-post</label>
                    <input type="email" class="madhat-input" name="client_email" id="client_email" required placeholder="näide@email.ee" 
                           oninvalid="this.setCustomValidity('Palun täida see väli')" oninput="this.setCustomValidity('')">
                </div>
                <div>
                    <label class="madhat-label">Telefon</label>
                    <input type="tel" class="madhat-input" name="client_phone" id="client_phone" required placeholder="+372..." 
                           oninvalid="this.setCustomValidity('Palun täida see väli')" oninput="this.setCustomValidity('')">
                </div>
            </div>

            <hr class="madhat-divider">

            <div class="madhat-mb" style="margin-top:25px;">
                <label class="madhat-label">Materjalid ja Mõõdud (1cm täpsus)</label>
                
                <div class="measurements-header">
                    <label>Materjal</label>
                    <label>Pinna nimetus</label>
                    <label>Laius (cm)</label>
                    <label>Kõrgus (cm)</label>
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
                <textarea name="client_info" id="client_info" class="madhat-textarea" rows="3" placeholder="Täpsustavad soovid / kaaspaketi tüüp / asukoht / tellingu vajadus / ligipääs jne..." oninput="saveState()"></textarea>
            </div>
            
            <div class="madhat-mb">
                <label class="madhat-label">Failid / Pildid / Joonised</label>
                <input type="file" name="client_files[]" multiple style="font-size:0.9em; padding:10px 0;">
            </div>

            <div class="submit-area">
                <button type="submit" class="madhat-btn btn-submit">SAADA PÄRING</button>
                
                <label class="confirm-box" id="confirm-label">
                    <input type="checkbox" name="confirm_measurements" id="confirm_check" required 
                           oninvalid="this.setCustomValidity('Palun märgi see kast')" oninput="this.setCustomValidity('')">
                    <span>Kinnitan mõõtude täpsuse +/- 1cm</span>
                </label>
            </div>
        </form>
    </div>

    <script>
    const WASTAGE_PCT = <?php echo floatval($wastage); ?>;
    const WIN_DATA = <?php echo $json_win; ?>;
    const INT_DATA = <?php echo $json_int; ?>;

    function buildOptions(selected = '') {
        let html = '<option value="" disabled selected>-- Vali materjal --</option>';
        html += '<optgroup label="Aknakiled">';
        WIN_DATA.forEach(row => {
            const val = 'win|' + row.name;
            const isSel = (val === selected) ? 'selected' : '';
            html += `<option value="${val}" data-min="${row.min}" data-max="${row.max}" ${isSel}>${row.name}</option>`;
        });
        html += '</optgroup>';
        html += '<optgroup label="Sisustuskiled">';
        INT_DATA.forEach(row => {
            const val = 'int|' + row.name;
            const isSel = (val === selected) ? 'selected' : '';
            html += `<option value="${val}" data-min="${row.min}" data-max="${row.max}" ${isSel}>${row.name}</option>`;
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

    function getCleanCm(val) {
        if(!val) return 0;
        // Eemaldame kõik mis pole number
        const clean = val.replace(/[^0-9]/g, '');
        return parseInt(clean) || 0;
    }

    function calcPrice() {
        let minTotal = 0; let maxTotal = 0; let hasArea = false;
        document.querySelectorAll('.item-row').forEach(row => {
            const wVal = row.querySelector('.input-w').value;
            const hVal = row.querySelector('.input-h').value;
            const qVal = row.querySelector('.input-q').value;
            const matSelect = row.querySelector('.input-mat');
            
            const option = matSelect.options[matSelect.selectedIndex];
            const pMin = option ? parseFloat(option.getAttribute('data-min') || 0) : 0;
            const pMax = option ? parseFloat(option.getAttribute('data-max') || 0) : 0;

            const w = getCleanCm(wVal);
            const h = getCleanCm(hVal);
            const q = parseInt(qVal) || 0;

            if (w > 0 && h > 0 && q > 0) {
                hasArea = true;
                const area = (w / 100) * (h / 100) * q;
                minTotal += area * pMin;
                maxTotal += area * pMax;
            }
        });

        const box = document.getElementById('price-box');
        const display = document.getElementById('price-display');
        if (hasArea && maxTotal > 0) {
            const finalMin = Math.round(minTotal * (1 + WASTAGE_PCT / 100));
            const finalMax = Math.round(maxTotal * (1 + WASTAGE_PCT / 100));
            display.textContent = `€${finalMin} - ${finalMax} + KM`;
            box.style.display = 'block';
        } else {
            box.style.display = 'none';
        }
    }

    function updateRowUI() {
        const rows = document.querySelectorAll('.item-row');
        rows.forEach((row, index) => {
            const numEl = row.querySelector('.row-number');
            if(numEl) numEl.textContent = 'Nr. ' + (index + 1);
            if (index === 0) row.classList.add('first-row');
            else row.classList.remove('first-row');
        });
    }

    let rIdx = 0;
    function addMRow(shouldSave = true, values = null) {
        let defaultMat = ''; 
        const rows = document.querySelectorAll('.item-row');
        if (rows.length > 0 && !values) {
            const lastRow = rows[rows.length - 1];
            defaultMat = lastRow.querySelector('.input-mat').value;
        }

        const div = document.createElement('div');
        div.className = 'item-row';
        
        const mat = values ? values.mat : defaultMat;
        const w = values ? values.w : '';
        const h = values ? values.h : '';
        const q = values ? values.q : '1';
        const l = values ? values.l : ''; 
        const optionsHtml = buildOptions(mat);
        const trashSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C12.5523 2 13 2.44772 13 3V4H19C19.5523 4 20 4.44772 20 5C20 5.55228 19.5523 6 19 6H5C4.44772 6 4 5.55228 4 5C4 4.44772 4.44772 4 5 4H11V3C11 2.44772 11.4477 2 12 2ZM6 8V20C6 21.1046 6.89543 22 8 22H16C17.1046 22 18 21.1046 18 20V8H6ZM9 10C9.55228 10 10 10.4477 10 11V19C10 19.5523 9.55228 20 9 20C8.44772 20 8 19.5523 8 19V11C8 10.4477 8.44772 10 9 10ZM15 10C15.5523 10 16 10.4477 16 11V19C16 19.5523 15.5523 20 15 20C14.4477 20 14 19.5523 14 19V11C14 10.4477 14.4477 10 15 10Z"></path></svg>`;

        div.innerHTML = `
            <span class="row-number"></span>
            <div class="item-grid">
                <div class="grid-mat">
                    <span class="mobile-label">Materjal</span>
                    <select class="madhat-select input-mat" name="items[${rIdx}][mat]" required onchange="saveState(); calcPrice();" oninvalid="this.setCustomValidity('Palun täida see väli')" oninput="this.setCustomValidity('')">
                        ${optionsHtml}
                    </select>
                </div>
                <div class="grid-n">
                    <span class="mobile-label">Pinna nimetus</span>
                    <input type="text" class="madhat-input input-l" name="items[${rIdx}][l]" value="${l}" placeholder="Nimetus" oninput="saveState()">
                </div>
                <div class="grid-w">
                    <span class="mobile-label">Laius (cm)</span>
                    <input type="number" step="1" class="madhat-input input-w" name="items[${rIdx}][w]" value="${w}" placeholder="Laius" required onkeydown="return event.key !== ',' && event.key !== '.'" onchange="calcPrice(); saveState()" oninvalid="this.setCustomValidity('Palun täida see väli')" oninput="this.setCustomValidity('')">
                </div>
                <div class="grid-h">
                    <span class="mobile-label">Kõrgus (cm)</span>
                    <input type="number" step="1" class="madhat-input input-h" name="items[${rIdx}][h]" value="${h}" placeholder="Kõrgus" required onkeydown="return event.key !== ',' && event.key !== '.'" onchange="calcPrice(); saveState()" oninvalid="this.setCustomValidity('Palun täida see väli')" oninput="this.setCustomValidity('')">
                </div>
                <div class="grid-q">
                    <span class="mobile-label">Kogus</span>
                    <input type="number" class="madhat-input input-q" name="items[${rIdx}][q]" value="${q}" required onchange="calcPrice(); saveState()" oninvalid="this.setCustomValidity('Palun täida see väli')" oninput="this.setCustomValidity('')">
                </div>
                <div class="grid-d">
                    <span class="mobile-label">&nbsp;</span>
                    <button type="button" class="madhat-btn btn-remove" onclick="this.closest('.item-row').remove(); updateRowUI(); calcPrice(); saveState();">${trashSvg}</button>
                </div>
            </div>
        `;
        document.getElementById('madhat-rows').appendChild(div);
        rIdx++;
        updateRowUI();
        if(shouldSave) saveState();
    }
    
    function validateForm() {
        const cb = document.getElementById('confirm_check');
        if(!cb.checked) {
            document.getElementById('confirm-label').classList.add('confirm-error');
            setTimeout(() => document.getElementById('confirm-label').classList.remove('confirm-error'), 500);
            return false;
        }
        return true;
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
    
    // --- HINNA ARVUTAMINE E-MAILI JAOKS ---
    $wastage_pct = floatval(get_option('madhat_wastage_percent', 10));
    $win_data = json_decode(get_option('madhat_json_window'), true);
    $int_data = json_decode(get_option('madhat_json_interior'), true);
    
    function getPriceRange($name, $dataArr) {
        foreach($dataArr as $row) {
            if($row['name'] === $name) return ['min' => floatval($row['min']), 'max' => floatval($row['max'])];
        }
        return ['min' => 0, 'max' => 0];
    }

    $total_min = 0;
    $total_max = 0;
    
    $csv = "\xEF\xBB\xBFLength,Width,Qty,Material,Label,Enabled\n";
    $mail_body_items = "";

    if (is_array($items)) {
        foreach ($items as $i) {
            $mat_raw = sanitize_text_field($i['mat']); 
            $parts = explode('|', $mat_raw);
            $mat_type = isset($parts[0]) ? $parts[0] : 'win'; 
            $mat_name = isset($parts[1]) ? $parts[1] : 'Määramata';
            $offset = ($mat_type === 'int') ? 80 : 20;

            $prices = ($mat_type === 'win') ? getPriceRange($mat_name, $win_data) : getPriceRange($mat_name, $int_data);

            // UUS: AINULT TÄISARVUD PHP POOLEL
            $w_cm = intval($i['w']);
            $h_cm = intval($i['h']);
            $q = intval($i['q']);
            $l = sanitize_text_field($i['l']);
            
            $area = ($w_cm / 100) * ($h_cm / 100) * $q;
            $total_min += $area * $prices['min'];
            $total_max += $area * $prices['max'];

            $w_mm = $w_cm * 10; 
            $h_mm = $h_cm * 10;
            
            if ($w_mm <= 0 || $h_mm <= 0 || $q <= 0) continue;

            $mail_body_items .= "- $mat_name: $w_mm x $h_mm mm ($q tk) - $l\n";
            
            $csv_w = $w_mm + $offset;
            $csv_h = $h_mm + $offset;
            $l_csv = str_replace('"', '""', $l); 
            $mat_csv = str_replace('"', '""', $mat_name);
            $csv .= "$csv_h,$csv_w,$q,\"$mat_csv\",\"$l_csv\",true\n";
        }
    }
    
    $final_min = round($total_min * (1 + $wastage_pct / 100));
    $final_max = round($total_max * (1 + $wastage_pct / 100));
    
    $mail_txt = "UUS PÄRING VEEBILEHELT\n\n";
    $mail_txt .= "TEEMA: $project\n";
    $mail_txt .= "Kontaktisik: $contact\n";
    $mail_txt .= "Email: $email\nTelefon: $phone\n";
    $mail_txt .= "Lisainfo: $info\n\n";
    $mail_txt .= "HINNANGULINE MAKSUMUS:\n";
    $mail_txt .= "€$final_min - $final_max + KM\n\n";
    $mail_txt .= "MATERJALID JA MÕÕDUD (teisendatud mm-ks):\n------------------------------------\n";
    $mail_txt .= $mail_body_items;

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