<?php
/**
 * Windy.app Wind Forecast - LoxBerry Plugin Web Interface
 */

require_once "loxberry_system.php";
require_once "loxberry_web.php";

// Plugin config file
$cfgfile = "$lbpconfigdir/windyapp.cfg";
$datafile = "$lbpdatadir/current.json";

// Handle form save
if (isset($_POST['saveformdata']) && $_POST['saveformdata'] == '1') {
    $cfg = array();
    $cfg['WINDYAPP'] = array(
        'LAT' => $_POST['lat'] ?? '47.85397',
        'LON' => $_POST['lon'] ?? '13.0266',
        'MODELS' => '"' . implode(';', $_POST['models'] ?? array('ECMWF')) . '"',
        'PREFERRED_MODEL' => $_POST['preferred_model'] ?? 'ECMWF',
        'CRON' => $_POST['cron'] ?? '15',
        'LANG' => 'de',
    );
    $cfg['SERVER'] = array(
        'SENDUDP' => isset($_POST['sendudp']) ? '1' : '0',
        'UDPPORT' => $_POST['udpport'] ?? '7001',
        'MSNO' => $_POST['msno'] ?? '1',
    );

    // Write config file
    $content = "; Windy.app Wind Forecast Plugin Configuration\n\n";
    foreach ($cfg as $section => $values) {
        $content .= "[$section]\n";
        foreach ($values as $key => $val) {
            $content .= "$key=$val\n";
        }
        $content .= "\n";
    }
    file_put_contents($cfgfile, $content);

    // Update cron symlink
    $cron = intval($cfg['WINDYAPP']['CRON']);
    $cronintervals = array(1, 3, 5, 10, 15, 30, 60);
    foreach ($cronintervals as $interval) {
        $cronlink = "$lbhomedir/system/cron/cron.{$interval}min/windyapp";
        if (file_exists($cronlink)) {
            unlink($cronlink);
        }
    }
    $crondir = "$lbhomedir/system/cron/cron.{$cron}min";
    if (is_dir($crondir)) {
        symlink("$lbhomedir/bin/plugins/$lbpplugindir/cronjob.sh", "$crondir/windyapp");
    }
}

// Handle fetch request
if (isset($_GET['action']) && $_GET['action'] == 'fetch') {
    header('Content-Type: application/json');
    $output = shell_exec("python3 $lbhomedir/bin/plugins/$lbpplugindir/fetch.py 2>&1");
    echo json_encode(array('output' => $output, 'error' => 0));
    exit;
}

// Handle data request
if (isset($_GET['action']) && $_GET['action'] == 'data') {
    header('Content-Type: application/json');
    if (file_exists($datafile)) {
        echo file_get_contents($datafile);
    } else {
        echo json_encode(array('error' => 'No data yet'));
    }
    exit;
}

// Read current config
$lat = '47.85397';
$lon = '13.0266';
$models_selected = array('ECMWF', 'AROME', 'GFS27', 'ICONGLOBAL', 'HRRR');
$preferred_model = 'ECMWF';
$cron = '15';
$sendudp = '1';
$udpport = '7001';
$msno = '1';

if (file_exists($cfgfile)) {
    $ini = parse_ini_file($cfgfile, true);
    if (isset($ini['WINDYAPP'])) {
        $w = $ini['WINDYAPP'];
        $lat = $w['LAT'] ?? $lat;
        $lon = $w['LON'] ?? $lon;
        $models_str = trim($w['MODELS'] ?? '', '"\'');
        if ($models_str) {
            $models_selected = array_map('trim', explode(';', $models_str));
        }
        $preferred_model = $w['PREFERRED_MODEL'] ?? $preferred_model;
        $cron = $w['CRON'] ?? $cron;
    }
    if (isset($ini['SERVER'])) {
        $s = $ini['SERVER'];
        $sendudp = $s['SENDUDP'] ?? $sendudp;
        $udpport = $s['UDPPORT'] ?? $udpport;
        $msno = $s['MSNO'] ?? $msno;
    }
}

// Available models
$all_models = array(
    'ECMWF' => 'ECMWF (European Centre)',
    'AROME' => 'AROME (Meteo France)',
    'GFS27' => 'GFS 27km (NOAA)',
    'ICONGLOBAL' => 'ICON Global (DWD)',
    'HRRR' => 'HRRR (High-Res Rapid)',
    'NAM' => 'NAM (North America)',
    'MFWAM' => 'MFWAM (Wave)',
    'MYOCEAN' => 'MyOcean (Marine)',
    'UVI' => 'UVI (UV Index)',
);

// Read Miniservers from LoxBerry
$miniservers = array();
if (function_exists('LBSystem::get_miniservers')) {
    $miniservers = LBSystem::get_miniservers();
} else {
    // Fallback: read from general.cfg
    $gencfg = "$lbhomedir/config/system/general.cfg";
    if (file_exists($gencfg)) {
        $gen = parse_ini_file($gencfg, true);
        for ($i = 1; $i <= 10; $i++) {
            $section = "MINISERVER$i";
            if (isset($gen[$section]) && !empty($gen[$section]['IPADDRESS'])) {
                $miniservers[$i] = array(
                    'Name' => $gen[$section]['NAME'] ?? "Miniserver $i",
                    'IPAddress' => $gen[$section]['IPADDRESS'],
                );
            }
        }
    }
}

// Navbar
$navbar[0]['Name'] = 'Settings';
$navbar[0]['URL'] = 'index.php';
$navbar[0]['active'] = true;
$navbar[1]['Name'] = 'Wind Data';
$navbar[1]['URL'] = 'index.php?page=data';

$page = $_GET['page'] ?? 'settings';
if ($page == 'data') {
    $navbar[0]['active'] = false;
    $navbar[1]['active'] = true;
}

LBWeb::lbheader("Windy.app Wind Forecast", "https://github.com/amyc-codes/loxberry-plugin-windyapp", "");

if ($page == 'data'):
?>

<!-- Wind Data Page -->
<div style="padding: 15px;">
    <div style="display:flex; align-items:center; gap:15px; margin-bottom:20px;">
        <img width="64" height="64" src="/plugins/windyapp/images/icon_64.png" alt="" onerror="this.style.display='none'">
        <div>
            <h2 style="margin:0;">Current Wind Data</h2>
            <small>Last fetch results from all models</small>
        </div>
    </div>

    <a id="btnfetch" data-role="button" data-inline="true" data-mini="true" data-icon="refresh"
       style="background-color: rgba(67, 236, 48, 0.4);" href="javascript:fetchnow()">
        Fetch wind data now...
    </a>
    <span id="fetchstate"></span>

    <div id="winddata" style="margin-top:20px;">
        <p><i>Loading...</i></p>
    </div>
</div>

<script>
function fetchnow() {
    $('#btnfetch').addClass('ui-disabled');
    $('#fetchstate').css('color','blue').html('Fetching...');
    $.ajax({
        url: 'index.php?action=fetch',
        type: 'GET',
        dataType: 'json',
        timeout: 60000,
        success: function(data) {
            $('#fetchstate').css('color','green').html('Done!');
            loaddata();
        },
        error: function() {
            $('#fetchstate').css('color','red').html('Error!');
        },
        complete: function() {
            $('#btnfetch').removeClass('ui-disabled');
        }
    });
}

function loaddata() {
    $.getJSON('index.php?action=data', function(data) {
        if (data.error) {
            $('#winddata').html('<p><i>' + data.error + '</i></p>');
            return;
        }

        var html = '<table class="formtable" border="0" width="100%" cellpadding="5">';
        html += '<tr><td colspan="5"><h3>🌊 Consensus (All Models Average)</h3></td></tr>';
        html += '<tr><th>Speed</th><th>Knots</th><th>km/h</th><th>Direction</th><th>Models</th></tr>';
        var c = data.consensus;
        html += '<tr>';
        html += '<td><b>' + (c.wind_speed_ms || '-') + ' m/s</b></td>';
        html += '<td><b>' + (c.wind_speed_kn || '-') + ' kn</b></td>';
        html += '<td><b>' + (c.wind_speed_kmh || '-') + ' km/h</b></td>';
        html += '<td><b>' + (c.wind_direction_name || '-') + ' (' + (c.wind_direction_deg || '-') + '°)</b></td>';
        html += '<td>' + (c.models_count || 0) + '</td>';
        html += '</tr>';

        html += '<tr><td colspan="5"><hr></td></tr>';
        html += '<tr><td colspan="5"><h3>📊 Per-Model Data</h3></td></tr>';
        html += '<tr><th>Model</th><th>Speed (kn)</th><th>Direction</th><th>Updated</th><th>Forecast Hour</th></tr>';

        var pref = data.preferred_model;
        for (var model in data.models) {
            var m = data.models[model];
            var star = (model === pref) ? ' ⭐' : '';
            html += '<tr>';
            html += '<td><b>' + model + star + '</b></td>';
            html += '<td>' + (m.wind_speed_kn || '-') + ' kn (' + (m.wind_speed_ms || '-') + ' m/s)</td>';
            html += '<td>' + (m.wind_direction_name || '-') + ' (' + (m.wind_direction_deg || '-') + '°)</td>';
            html += '<td>' + (m.model_update_time ? new Date(m.model_update_time * 1000).toLocaleString() : '-') + '</td>';
            html += '<td>' + (m.spot_date || '-') + ' ' + (m.spot_hour || '-') + ':00</td>';
            html += '</tr>';
        }
        html += '</table>';
        html += '<p style="margin-top:15px; color:#888;"><small>Last fetch: ' + (data.fetched_at || 'unknown') + '</small></p>';

        $('#winddata').html(html);
    });
}

$(document).ready(function() { loaddata(); });
</script>

<?php else: ?>

<!-- Settings Page -->
<div style="padding: 15px;">
    <div style="display:flex; align-items:center; gap:15px; margin-bottom:20px;">
        <img width="64" height="64" src="/plugins/windyapp/images/icon_64.png" alt="" onerror="this.style.display='none'">
        <div>
            <h2 style="margin:0;">Windy.app Wind Forecast</h2>
            <small>Multi-model wind data for your Miniserver</small>
        </div>
    </div>

    <form method="post" data-ajax="false" name="main_form" id="main_form" action="index.php">
        <input type="hidden" name="saveformdata" value="1">

        <table class="formtable" border="0" width="100%" cellpadding="10">
            <tr><td colspan="3"><h3>📍 Location</h3></td></tr>
            <tr>
                <td width="25%"><label>Latitude</label></td>
                <td width="50%">
                    <input type="text" name="lat" id="lat" value="<?php echo htmlspecialchars($lat); ?>"
                           placeholder="47.85397">
                </td>
                <td width="25%"><small>Decimal degrees</small></td>
            </tr>
            <tr>
                <td><label>Longitude</label></td>
                <td>
                    <input type="text" name="lon" id="lon" value="<?php echo htmlspecialchars($lon); ?>"
                           placeholder="13.0266">
                </td>
                <td><small>Decimal degrees</small></td>
            </tr>

            <tr><td colspan="3"><hr></td></tr>
            <tr><td colspan="3"><h3>🌬️ Weather Models</h3></td></tr>
            <tr>
                <td><label>Active Models</label></td>
                <td>
                    <fieldset data-role="controlgroup">
                    <?php foreach ($all_models as $key => $label): ?>
                        <label>
                            <input type="checkbox" name="models[]" value="<?php echo $key; ?>"
                                <?php echo in_array($key, $models_selected) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </label>
                    <?php endforeach; ?>
                    </fieldset>
                </td>
                <td><small>ECMWF &amp; AROME are best for Central Europe</small></td>
            </tr>
            <tr>
                <td><label>Preferred Model</label></td>
                <td>
                    <select name="preferred_model" id="preferred_model" data-mini="true">
                    <?php foreach ($all_models as $key => $label): ?>
                        <option value="<?php echo $key; ?>"
                            <?php echo ($key == $preferred_model) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                    </select>
                </td>
                <td><small>Sent as default values without model prefix</small></td>
            </tr>

            <tr><td colspan="3"><hr></td></tr>
            <tr><td colspan="3"><h3>⏱️ Schedule</h3></td></tr>
            <tr>
                <td><label>Fetch Interval</label></td>
                <td>
                    <select name="cron" id="cron" data-mini="true">
                        <option value="5" <?php echo ($cron=='5')?'selected':''; ?>>Every 5 Minutes</option>
                        <option value="10" <?php echo ($cron=='10')?'selected':''; ?>>Every 10 Minutes</option>
                        <option value="15" <?php echo ($cron=='15')?'selected':''; ?>>Every 15 Minutes</option>
                        <option value="30" <?php echo ($cron=='30')?'selected':''; ?>>Every 30 Minutes</option>
                        <option value="60" <?php echo ($cron=='60')?'selected':''; ?>>Every 60 Minutes</option>
                    </select>
                </td>
                <td><small>How often to fetch new data</small></td>
            </tr>

            <tr><td colspan="3"><hr></td></tr>
            <tr><td colspan="3"><h3>📡 Miniserver</h3></td></tr>
            <tr>
                <td><label>Miniserver</label></td>
                <td>
                    <select name="msno" id="msno" data-mini="true">
                    <?php foreach ($miniservers as $idx => $ms): ?>
                        <option value="<?php echo $idx; ?>"
                            <?php echo ($idx == $msno) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ms['Name'] . ' (' . $ms['IPAddress'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (empty($miniservers)): ?>
                        <option value="1">Default Miniserver</option>
                    <?php endif; ?>
                    </select>
                </td>
                <td><small>Target Miniserver</small></td>
            </tr>
            <tr>
                <td><label>Send UDP</label></td>
                <td>
                    <select name="sendudp" id="sendudp" data-role="flipswitch">
                        <option value="0" <?php echo ($sendudp=='0')?'selected':''; ?>>Off</option>
                        <option value="1" <?php echo ($sendudp=='1')?'selected':''; ?>>On</option>
                    </select>
                </td>
                <td><small>Send data via UDP to Miniserver</small></td>
            </tr>
            <tr>
                <td><label>UDP Port</label></td>
                <td>
                    <input type="text" name="udpport" id="udpport"
                           value="<?php echo htmlspecialchars($udpport); ?>" placeholder="7001">
                </td>
                <td><small>Must match Virtual UDP Input port on Miniserver</small></td>
            </tr>
        </table>

        <div style="text-align:right; margin-top:20px;">
            <button type="submit" data-role="button" data-inline="true" data-mini="true" data-icon="check">
                Save
            </button>
        </div>
    </form>
</div>

<?php endif; ?>

<?php
LBWeb::lbfooter();
?>
