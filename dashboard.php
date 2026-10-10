<?php
// dashboard.php
// Session guard + role-based access control.
// admin → full control (ON/OFF + dimming)
// user  → view only (no buttons shown)

session_start();

// Not logged in → back to login
if (empty($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';

// Fetch the logged-in user's name and role
$stmt = $conn->prepare("SELECT name, role FROM users WHERE email = ?");
$stmt->execute([$_SESSION['email']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Email in session but not in DB — force logout
    session_destroy();
    header("Location: login.php");
    exit;
}

$isAdmin   = ($user['role'] === 'admin');
$userName  = htmlspecialchars($user['name']);
$userRole  = htmlspecialchars($user['role']);
$conn = null;
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>iot based public light fault detection and reporting system</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    *{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
    body{margin:0;background:#f7f9fb;color:#222;display:flex;flex-direction:column;min-height:100vh}
    header{
      background:#0b76ef;
      color:#fff;
      padding:12px 20px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      flex-wrap:wrap;
      gap:8px;
    }
    header h1{margin:0;font-size:1.3rem}
    .header-right{display:flex;align-items:center;gap:12px}
    .user-info{font-size:0.85rem;opacity:0.9}
    .role-badge{
      padding:2px 8px;border-radius:10px;font-size:0.75rem;font-weight:bold;
      background:<?php echo $isAdmin ? '#ffd700' : '#cce5ff'; ?>;
      color:<?php echo $isAdmin ? '#333' : '#004085'; ?>;
    }
    .logout-btn{
      background:#fff;color:#0b76ef;border:none;
      padding:6px 12px;border-radius:5px;font-weight:bold;cursor:pointer;transition:0.3s;
    }
    .logout-btn:hover{background:#e6e6e6}
    main{flex:1;padding:20px;max-width:1100px;margin:0 auto;width:100%}
    .cards{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap}
    .card{background:#fff;padding:16px;border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,0.06);flex:1;min-width:160px;text-align:center}
    .card h3{margin:0;font-size:1.6rem}
    .table-section,.chart-section,.alerts-section{background:#fff;padding:16px;border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,0.04);margin-bottom:20px}
    table{width:100%;border-collapse:collapse}
    table th,table td{padding:8px;border-bottom:1px solid #eee;text-align:left}
    .status-ON{color:green;font-weight:600}
    .status-OFF{color:#bb8800;font-weight:600}
    .status-FAILED{color:red;font-weight:700}
    .status-OVERCURRENT{color:#fff;background:red;font-weight:700;padding:2px 6px;border-radius:4px;animation:blink 0.8s step-start infinite}
    @keyframes blink{50%{background:#800000}}
    footer{padding:10px;text-align:center;background:#fff}
    #alertsList{max-height:160px;overflow:auto}
    @media(max-width:700px){.cards{flex-direction:column}}

    /* --- Admin-only control styles --- */
    .ctrl-btn{padding:4px 10px;border:none;border-radius:4px;cursor:pointer;font-size:0.82rem;font-weight:bold;margin:0 2px}
    .btn-on{background:#28a745;color:#fff}.btn-on:hover{background:#1e7e34}
    .btn-off{background:#dc3545;color:#fff}.btn-off:hover{background:#b02a37}
    .btn-on:disabled,.btn-off:disabled{opacity:0.5;cursor:not-allowed}
    .ctrl-msg{font-size:0.78rem;color:#555;margin-left:6px}
    .dim-group{display:inline-flex;gap:3px;margin-left:6px;vertical-align:middle}
    .btn-dim{padding:3px 8px;border:none;border-radius:4px;cursor:pointer;font-size:0.78rem;font-weight:bold;transition:0.2s}
    .btn-dim-low{background:#f0c040;color:#333}.btn-dim-low:hover{background:#d4a800}
    .btn-dim-medium{background:#fd8c00;color:#fff}.btn-dim-medium:hover{background:#c46e00}
    .btn-dim-high{background:#0b76ef;color:#fff}.btn-dim-high:hover{background:#0056b3}
    .btn-dim:disabled{opacity:0.5;cursor:not-allowed}

    /* --- View-only badge for normal users --- */
    .view-only-badge{
      font-size:0.78rem;color:#888;font-style:italic;
    }
    .view-only-notice{
      background:#fff3cd;border:1px solid #ffc107;color:#856404;
      padding:10px 16px;border-radius:6px;margin-bottom:16px;font-size:0.9rem;
    }
  </style>
</head>
<body>

<header>
  <h1>iot based public light fault detection and reporting system</h1>
  <div class="header-right">
    <span class="user-info">
      <svg style="width:14px;height:14px;vertical-align:middle;margin-right:4px" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
      <?= $userName ?> &nbsp;<span class="role-badge"><?= strtoupper($userRole) ?></span>
    </span>
    <button class="logout-btn" onclick="logout()">Logout</button>
  </div>
</header>

<main>

  <?php if (!$isAdmin): ?>
  <div class="view-only-notice">
    <svg style="width:15px;height:15px;vertical-align:middle;margin-right:5px" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.7 7.6 1 12c1.7 4.4 6 7.5 11 7.5s9.3-3.1 11-7.5C21.3 7.6 17 4.5 12 4.5zm0 12.5c-2.8 0-5-2.2-5-5s2.2-5 5-5 5 2.2 5 5-2.2 5-5 5zm0-8c-1.7 0-3 1.3-3 3s1.3 3 3 3 3-1.3 3-3-1.3-3-3-3z"/></svg>
    You are logged in as a <b>viewer</b>. You can monitor all streetlights in real time but cannot perform control actions. Contact an administrator to request changes.
  </div>
  <?php endif; ?>

  <section class="cards">
    <div class="card"><h3 id="total">--</h3><p>Total Lights</p></div>
    <div class="card"><h3 id="onCount">--</h3><p>Lights ON</p></div>
    <div class="card"><h3 id="offCount">--</h3><p>Lights OFF</p></div>
    <div class="card"><h3 id="failedCount">--</h3><p>Failures</p></div>
  </section>

  <section class="table-section">
    <h2>Live Devices</h2>
    <table id="devicesTable">
      <thead>
        <tr>
          <th>Device ID</th>
          <th>Current (A)</th>
          <th>Brightness</th>
          <th>Status</th>
          <th>Last Seen</th>
          <th><?= $isAdmin ? 'Control' : 'Access' ?></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </section>

  <section class="chart-section">
    <h2>Device History (select device)</h2>
    <select id="deviceSelect"><option value="">-- Select device --</option></select>
    <canvas id="historyChart" width="800" height="300"></canvas>
  </section>

  <section class="alerts-section">
    <h2>Recent Alerts</h2>
    <div id="alertsList">Loading...</div>
  </section>

</main>

<footer>
  <small>© 2026 iot based public light fault detection and reporting system</small>
</footer>

<script>
  // Role passed from PHP — controls whether control buttons are rendered
  const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

  let devices = [];
  let chart;

  function logout() {
    if (confirm("Are you sure you want to logout?")) {
      window.location.href = "logout.php";
    }
  }

  function updateCounters() {
    document.getElementById("total").textContent = devices.length;
    document.getElementById("onCount").textContent = devices.filter(d=>d.status==="ON").length;
    document.getElementById("offCount").textContent = devices.filter(d=>d.status==="OFF").length;
    document.getElementById("failedCount").textContent = devices.filter(d=>d.status==="FAILED"||d.status==="FAIL"||d.status==="OVERCURRENT").length;
  }

  // Send dim level — admin only
  async function sendDim(device_id, dim_level, btn) {
    const row = btn.closest('tr');
    row.querySelectorAll('.btn-dim,.ctrl-btn').forEach(b => b.disabled = true);
    const msgSpan = row.querySelector('.ctrl-msg');
    msgSpan.style.color = '#555';
    msgSpan.textContent = 'Setting...';
    try {
      const res  = await fetch('set_dim.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `device_id=${encodeURIComponent(device_id)}&dim_level=${dim_level}`
      });
      const data = await res.json();
      if (data.status === 'ok') {
        const labels = {DIM_LOW:'Low 25%', DIM_MEDIUM:'Medium 50%', DIM_HIGH:'High 100%'};
        msgSpan.textContent = `✓ ${labels[dim_level]} set`;
        msgSpan.style.color = '#1a7f37';
      } else {
        msgSpan.textContent = '✗ ' + (data.message || 'Error');
        msgSpan.style.color = 'red';
      }
    } catch(err) {
      msgSpan.textContent = '✗ Network error';
      msgSpan.style.color = 'red';
    }
    setTimeout(() => {
      row.querySelectorAll('.btn-dim,.ctrl-btn').forEach(b => b.disabled = false);
      msgSpan.textContent = '';
    }, 3000);
  }

  // Send ON/OFF command — admin only
  async function sendCommand(device_id, command, btn) {
    const row = btn.closest('tr');
    row.querySelectorAll('.ctrl-btn').forEach(b => b.disabled = true);
    const msgSpan = row.querySelector('.ctrl-msg');
    msgSpan.style.color = '#555';
    msgSpan.textContent = 'Sending...';

    let data = null;
    for (let attempt = 1; attempt <= 2; attempt++) {
      try {
        const res = await fetch('control_light.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: `device_id=${encodeURIComponent(device_id)}&command=${command}`
        });
        data = await res.json();
        break;
      } catch(err) {
        if (attempt === 2) {
          msgSpan.textContent = '⚠️ Network unstable. Check Recent Alerts.';
          msgSpan.style.color = '#bb8800';
          setTimeout(() => {
            row.querySelectorAll('.ctrl-btn').forEach(b => b.disabled = false);
            msgSpan.textContent = '';
          }, 5000);
          return;
        }
        await new Promise(r => setTimeout(r, 1000));
      }
    }

    if (data && data.status === 'ok') {
      let feedback = `✓ ${command} sent`;
      if (data.email_sent)       feedback += ' | <svg style="width:11px;height:11px;vertical-align:middle" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/></svg> Email sent';
      else if (data.email_error) feedback += ' | Email failed';
      if (data.sms_sent)         feedback += ' | <svg style="width:11px;height:11px;vertical-align:middle" viewBox="0 0 24 24" fill="currentColor"><path d="M17 1.01 7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg> SMS sent';
      else if (data.sms_error)   feedback += ' | SMS failed';
      msgSpan.innerHTML = feedback;
      msgSpan.style.color = '#1a7f37';
    } else {
      msgSpan.textContent = '✗ ' + (data?.message || 'Error');
      msgSpan.style.color = 'red';
    }
    setTimeout(() => {
      row.querySelectorAll('.ctrl-btn').forEach(b => b.disabled = false);
      msgSpan.textContent = '';
    }, 4000);
  }

  function buildControlCell(dev) {
    if (IS_ADMIN) {
      // Full control: ON/OFF + dimming
      return `
        <button class="ctrl-btn btn-on"
          onclick="sendCommand('${dev.device_id}','ON',this)">ON</button>
        <button class="ctrl-btn btn-off"
          onclick="sendCommand('${dev.device_id}','OFF',this)">OFF</button>
        <span class="dim-group">
          <button class="btn-dim btn-dim-low"
            onclick="sendDim('${dev.device_id}','DIM_LOW',this)" title="Low brightness 25%"><svg style="width:11px;height:11px;vertical-align:middle" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2 12h2M20 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/></svg> Low</button>
          <button class="btn-dim btn-dim-medium"
            onclick="sendDim('${dev.device_id}','DIM_MEDIUM',this)" title="Medium brightness 50%"><svg style="width:11px;height:11px;vertical-align:middle" viewBox="0 0 24 24" fill="currentColor"><path d="M12 7a5 5 0 1 0 0 10A5 5 0 0 0 12 7zm0-5v2m0 18v-2m7.07-13.07-1.41 1.41M6.34 17.66l-1.41 1.41M21 12h-2M5 12H3m15.07 7.07-1.41-1.41M6.34 6.34 4.93 4.93"/></svg> Med</button>
          <button class="btn-dim btn-dim-high"
            onclick="sendDim('${dev.device_id}','DIM_HIGH',this)" title="Full brightness 100%"><svg style="width:11px;height:11px;vertical-align:middle" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4a8 8 0 1 0 0 16A8 8 0 0 0 12 4zm0-2v2m0 18v-2m8-8h-2M6 12H4m13.66-5.66-1.41 1.41M7.76 16.24l-1.41 1.41m11.31 0-1.41-1.41M7.76 7.76 6.34 6.34"/></svg> High</button>
        </span>
        <span class="ctrl-msg"></span>
      `;
    } else {
      // View only — no buttons
      return `<span class="view-only-badge"><svg style="width:12px;height:12px;vertical-align:middle;margin-right:3px" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.7 7.6 1 12c1.7 4.4 6 7.5 11 7.5s9.3-3.1 11-7.5C21.3 7.6 17 4.5 12 4.5zm0 12.5c-2.8 0-5-2.2-5-5s2.2-5 5-5 5 2.2 5 5-2.2 5-5 5zm0-8c-1.7 0-3 1.3-3 3s1.3 3 3 3 3-1.3 3-3-1.3-3-3-3z"/></svg>View only</span>`;
    }
  }

  function loadTable() {
    const tbody = document.querySelector("#devicesTable tbody");
    tbody.innerHTML = "";
    devices.forEach(dev => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${dev.device_id}</td>
        <td>${dev.current.toFixed(2)}</td>
        <td>${dev.brightness}</td>
        <td class="status-${dev.status}">${dev.status}</td>
        <td>${dev.timestamp}</td>
        <td>${buildControlCell(dev)}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  function loadAlerts() {
    fetch('get_alerts.php')
      .then(r => r.json())
      .then(json => {
        const div = document.getElementById("alertsList");
        if (!json.data || json.data.length === 0) {
          div.innerHTML = "<p>No alerts</p>";
          return;
        }
        div.innerHTML = json.data.map(a => {
          const svgFire   = '<svg style="width:13px;height:13px;vertical-align:middle;margin-right:3px" viewBox="0 0 24 24" fill="#dc3545"><path d="M13.5 0.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67zM11.71 19c-1.78 0-3.22-1.4-3.22-3.14 0-1.62 1.05-2.76 2.81-3.12 1.77-.36 3.6-1.21 4.62-2.58.39 1.29.59 2.65.59 4.04 0 2.65-2.15 4.8-4.8 4.8z"/></svg>';
          const svgFail   = '<svg style="width:13px;height:13px;vertical-align:middle;margin-right:3px" viewBox="0 0 24 24" fill="#dc3545"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>';
          const svgOn     = '<svg style="width:13px;height:13px;vertical-align:middle;margin-right:3px" viewBox="0 0 24 24" fill="#28a745"><circle cx="12" cy="12" r="5"/><path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72 1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>';
          const svgOff    = '<svg style="width:13px;height:13px;vertical-align:middle;margin-right:3px" viewBox="0 0 24 24" fill="#bb8800"><path d="M12 3a9 9 0 1 0 0 18A9 9 0 0 0 12 3zm0 16a7 7 0 1 1 0-14 7 7 0 0 1 0 14z"/></svg>';
          const icon = a.alert_type.includes('OVERCURRENT') ? svgFire :
                       a.alert_type.includes('FAILURE')     ? svgFail :
                       a.alert_type.includes('RECOVERED')   ? svgOn   :
                       a.alert_type.includes('ON')          ? svgOn   : svgOff;
          return `<p>${icon} <b>${a.device_id}</b> — ${a.alert_type} — ${a.message} <small>(${a.sent_at})</small></p>`;
        }).join('');
      })
      .catch(() => {
        document.getElementById("alertsList").innerHTML = "<p>Error loading alerts</p>";
      });
  }

  function loadDeviceSelect() {
    const sel = document.getElementById("deviceSelect");
    const prev = sel.value;
    sel.innerHTML = '<option value="">-- Select device --</option>';
    devices.forEach(d => {
      const opt = document.createElement("option");
      opt.value = d.device_id;
      opt.textContent = d.device_id;
      if (d.device_id === prev) opt.selected = true;
      sel.appendChild(opt);
    });
    sel.onchange = () => { if (sel.value) loadChart(sel.value); };
  }

  function loadChart(deviceId) {
    const ctx = document.getElementById("historyChart").getContext("2d");
    fetch('get_history.php?device_id=' + encodeURIComponent(deviceId))
      .then(r => r.json())
      .then(json => {
        if (!json.data || json.data.length === 0) return;
        const labels     = json.data.map(d => d.timestamp.substring(11, 19));
        const brightness = json.data.map(d => d.status === 'OFF' ? 0 : parseInt(d.brightness));
        const currents   = json.data.map(d => d.status === 'OFF' ? 0 : Math.abs(parseFloat(d.current)).toFixed(2));
        const statuses   = json.data.map(d => d.status);

        // Prepend a 0-origin point so lines always start from bottom-left
        const allLabels     = ['', ...labels];
        const allBrightness = [0, ...brightness];
        const allCurrents   = [0, ...currents];
        const allStatuses   = [statuses[0] || 'ON', ...statuses];

        const pointColors = allStatuses.map(s => {
          if (s === 'ON')          return '#28a745';
          if (s === 'OFF')         return '#aaaaaa';
          if (s === 'FAILED')      return '#dc3545';
          if (s === 'OVERCURRENT') return '#ff6600';
          return '#aaaaaa';
        });
        // Hide the artificial origin point dot
        const pointRadii = allStatuses.map((_, i) => i === 0 ? 0 : 5);

        const statusBar = allStatuses.map(() => 8);

        if (chart) chart.destroy();
        chart = new Chart(ctx, {
          type: "line",
          data: {
            labels: allLabels,
            datasets: [
              {
                label: "Brightness (0-255)",
                data: allBrightness,
                fill: false,
                borderColor: "#0b76ef",
                borderWidth: 2,
                pointBackgroundColor: pointColors,
                pointRadius: pointRadii,
                tension: 0.3,
                yAxisID: 'yLeft',
                order: 2
              },
              {
                label: "Current (A)",
                data: allCurrents,
                fill: false,
                borderColor: "#28a745",
                borderWidth: 2,
                pointBackgroundColor: pointColors,
                pointRadius: pointRadii,
                tension: 0.3,
                yAxisID: 'yRight',
                order: 3
              },
              {
                label: 'Status',
                data: statusBar,
                fill: true,
                borderWidth: 0,
                pointRadius: 0,
                segment: {
                  backgroundColor: ctx => {
                    const s = allStatuses[ctx.p0DataIndex];
                    if (s === 'ON')          return '#28a74520';
                    if (s === 'OFF')         return '#cccccc30';
                    if (s === 'FAILED')      return '#dc354520';
                    if (s === 'OVERCURRENT') return '#ff660020';
                    return '#cccccc20';
                  }
                },
                tension: 0,
                yAxisID: 'yLeft',
                order: 1
              }
            ]
          },
          options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
              tooltip: {
                filter: item => item.dataset.label !== 'Status',
                callbacks: {
                  label: item => {
                    const idx = item.dataIndex;
                    if (idx === 0) return ''; // hide origin point tooltip
                    if (item.dataset.label === 'Brightness (0-255)')
                      return `  Brightness: ${allBrightness[idx]}`;
                    if (item.dataset.label === 'Current (A)')
                      return `  Current: ${allCurrents[idx]} A`;
                    return '';
                  },
                  afterBody: items => {
                    const idx = items[0].dataIndex;
                    if (idx === 0) return [];
                    const s = allStatuses[idx];
                    const icons = {ON:'[ON]', OFF:'[OFF]', FAILED:'[FAILED]', OVERCURRENT:'[OVERCURRENT]'};
                    return ['Status: ' + (icons[s] || s)];
                  }
                }
              },
              legend: {
                labels: { filter: item => item.text !== 'Status' }
              }
            },
            scales: {
              yLeft: {
                position: 'left',
                min: 0,
                max: 255,
                title: { display: true, text: 'Brightness (0-255)' },
                grid: { color: '#e8e8e8' }
              },
              yRight: {
                position: 'right',
                min: 0,
                title: { display: true, text: 'Current (A)' },
                grid: { drawOnChartArea: false }
              },
              x: { ticks: { maxTicksLimit: 10 } }
            }
          }
        });
      })
      .catch(err => console.error("Chart error:", err));
  }

  async function fetchDevices() {
    try {
      const res  = await fetch('fetchdata.php');
      const data = await res.json();
      devices = data.map(d => ({
        device_id:  d.device_id,
        current:    parseFloat(d.current),
        brightness: parseInt(d.brightness),
        status:     d.status,
        timestamp:  d.timestamp
      }));
      loadTable();
      updateCounters();
      loadAlerts();
      loadDeviceSelect();
    } catch(err) {
      console.error("Error fetching devices:", err);
      document.getElementById("alertsList").innerHTML = "<p>Error loading devices</p>";
    }
  }

  fetchDevices();
  setInterval(fetchDevices, 3000);
</script>
</body>
</html>
