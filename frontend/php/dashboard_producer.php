<?php
require __DIR__ . '/config.php';
require_role('producer');

$me = $_SESSION['user'];
$all = get_products();
$mine = array_filter($all, fn($r) => $r['owner'] === $me['username']);
$msg = '';

// ---- Handle actions: add / update / delete / approve ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $all = get_products(); // reload fresh

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $qty   = (int)($_POST['qty'] ?? 0);
        if ($name === '' || $price < 0 || $qty < 0) {
            $msg = "Please provide valid name, price ≥ 0, quantity ≥ 0.";
        } else {
            $id = next_product_id($all);

            $all[$id] = [
                'id'=>$id,
                'owner'=>$me['username'],
                'supplier'=>$me['username'],
                'consumer'=>$me['username'],
                'ownertx'=>$me['username'],
                'suppliertx'=>$me['username'],
                'name'=>$name,
                'price'=>$price,
                'qty'=>$qty,
                'status'=>'pending',
                'updated_at'=>now_iso()
            ];
            $msg = save_products($all) ? "Product added." : "Failed to add product.";
        }
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && isset($all[$id]) && $all[$id]['owner'] === $me['username']) {
            //  If approved, block edits (defense in depth)
            if (($all[$id]['status'] ?? '') === 'approved') {
                $msg = "Product #$id is approved and cannot be edited.";
            } else {
                $name = trim($_POST['name'] ?? $all[$id]['name']);
                $price = (float)($_POST['price'] ?? $all[$id]['price']);
                $qty   = (int)($_POST['qty'] ?? $all[$id]['qty']);
                if ($name === '' || $price < 0 || $qty < 0) {
                    $msg = "Please provide valid name, price ≥ 0, quantity ≥ 0.";
                } else {
                    $all[$id]['name']  = $name;
                    $all[$id]['price'] = $price;
                    $all[$id]['qty']   = $qty;
                    $all[$id]['updated_at'] = now_iso();
                    $msg = save_products($all) ? "Product #$id updated." : "Failed to update.";
                }
            }
        } else {
            $msg = "Not found or not your product.";
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && isset($all[$id]) && $all[$id]['owner'] === $me['username']) {
            // If approved, block deletes
            if (($all[$id]['status'] ?? '') === 'approved') {
                $msg = "Product #$id is approved and cannot be deleted.";
            } else {
                unset($all[$id]);
                $msg = save_products($all) ? "Product #$id deleted." : "Failed to delete.";
            }
        } else {
            $msg = "Not found or not your product.";
        }
    }

    if ($action === 'approve') {
        $id = (int)($_POST['id'] ?? 0);
        $txhash = trim($_POST['txhash'] ?? ''); // comes from JS after on-chain success

        $onchain = $_POST['onchain_id'] ?? '';
        if (ctype_digit($onchain)) {
            $all[$id]['onchain_id'] = (int)$onchain;
        }

        if ($id && isset($all[$id]) && $all[$id]['owner'] === $me['username']) {
            // Only allow pending -> approved here; unapprove remains allowed but doesn't require chain
            if (($all[$id]['status'] ?? '') !== 'approved') {
                // set approved
                $all[$id]['ownertx'] = $txhash;
                $all[$id]['status'] = 'approved';
                $all[$id]['updated_at'] = now_iso();
                $onchain_id = (int)($_POST['onchain_id'] ?? 0);
                if ($onchain_id > 0) {
                    $all[$id]['onchain_id'] = $onchain_id;
                }

                $saved = save_products($all);

                if ($saved) {
                    // Show tx hash if provided
                    if ($txhash !== '') {
                        $short = substr($txhash, 0, 10) . '…';
                        $msg = "Product #$id approved. On-chain tx: $short";
                    } else {
                        $msg = "Product #$id approved.";
                    }
                } else {
                    $msg = "Failed to update status.";
                }
            } else {
                // allow unapprove (no chain write)
                $all[$id]['status'] = 'pending';
                $all[$id]['updated_at'] = now_iso();
                $msg = save_products($all) ? "Product #$id marked pending." : "Failed to update status.";
            }
        } else {
            $msg = "Not found or not your product.";
        }
    }

    // Refresh filtered list after changes
    $mine = array_filter($all, fn($r) => $r['owner'] === $me['username']);
}

render_header("Producer Dashboard");
?>
<div class="bar">
  <div>
    <h1>Producer Dashboard</h1>
    <span class="tag"><?= h($me['role']) ?></span>
  </div>
  <div>
    <a href="login.php" class="btn-secondary" style="margin-right:8px;text-decoration:none;"><button class="btn-secondary">Home</button></a>
    <a href="logout.php"><button class="btn-danger">Log out</button></a>
  </div>
</div>

<p class="sub">Welcome, <b><?= h($me['username']) ?></b>. Manage your products below.</p>
<?php if ($msg): ?>
  <div class="<?= str_contains($msg,'fail') || str_contains(strtolower($msg),'not') ? 'msg' : 'ok' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<!-- Add product -->
<h2 style="margin:20px 0 8px;">Add Product</h2>
<form method="post" action="dashboard_producer.php">
  <input type="hidden" name="action" value="add">
  <div class="row2">
    <div>
      <label>Name</label>
      <input name="name" required placeholder="e.g., Fresh Apples">
    </div>
    <div>
      <label>Price</label>
      <input name="price" type="number" step="0.0001" min="0.0001" required placeholder="e.g., 0.0010">
    </div>
  </div>
  <div class="row2">
    <div>
      <label>Quantity</label>
      <input name="qty" type="number" step="1" min="0" required placeholder="e.g., 100">
    </div>
  </div>
  <button type="submit">Add</button>
  <p class="muted">New products start as <b>pending</b>. Approve to publish on chain.</p>
</form>

<!-- List / edit products -->
<h2 style="margin:24px 0 8px;">My New Products</h2>
<table>
  <?php if (empty($mine)): ?>
    <tr><td colspan="7" class="muted">No products yet.</td></tr>
  <?php else: ?>
  <tr>
    <th>ID</th><th>Name</th><th>Price</th><th>Qty</th><th>Status</th><th>Updated</th><th>Actions</th>
  </tr>
    <?php foreach ($mine as $r): ?>
    <?php if ($r['status'] === 'pending'): ?>
      <tr data-id="<?= h($r['id']) ?>"
          data-name="<?= h($r['name']) ?>"
          data-price="<?= h($r['price']) ?>"
          data-qty="<?= h($r['qty']) ?>">
        <td>#<?= h($r['id']) ?></td>
        <td>
          <?php if ($r['status'] !== 'pending'): ?>
            <!-- Approved: read-only -->
            <?= h($r['name']) ?>
          <?php else: ?>
            <!-- Editable only if NOT approved -->
            <form method="post" action="dashboard_producer.php" style="display:flex;gap:8px;align-items:center;">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= h($r['id']) ?>">
              <input name="name" value="<?= h($r['name']) ?>" style="max-width:220px">
              <input name="price" type="number" step="0.0001" min="0.0001" value="<?= h($r['price']) ?>" style="width:110px">
              <input name="qty" type="number" step="1" min="0" value="<?= h($r['qty']) ?>" style="width:90px">
               <?php if ($r['status']==='pending'): ?>
                     <button type="submit">Save</button>
                <?php endif; ?>
            </form>
          <?php endif; ?>
        </td>
        <td><?= h(number_format($r['price'], 4)) ?></td>
        <td><?= h($r['qty']) ?></td>
        <td>
          <span class="pill <?= $r['status']==='approved'?'ok':'pending' ?>">
            <?= h($r['status']) ?>
          </span>
        </td>
        <td class="muted"><?= h($r['updated_at']) ?></td>
        <td class="actions">
          <!-- Approve / Unapprove -->
          <form method="post" action="dashboard_producer.php" class="approve-form">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" value="<?= h($r['id']) ?>">
            <!-- JS will fill txhash only after chain success -->
            <input type="hidden" name="txhash" value="">
            <input type="hidden" name="onchain_id" value="">

            <?php if ($r['status']==='pending'): ?>
              <!-- IMPORTANT: this button triggers on-chain call first -->
              <button type="button" class="btn-onchain-approve">Approve </button>
            <?php endif; ?>
          </form>

          <!-- Delete: only if NOT approved -->
          <?php if ($r['status'] === 'pending'): ?>
            <form method="post" action="dashboard_producer.php" onsubmit="return confirm('Delete product #<?= h($r['id']) ?>?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= h($r['id']) ?>">
              <button type="submit" class="btn-danger">Delete</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</table>

<style>
div.tablecontainer {
  overflow-x: auto;
}

table {
  border-collapse: collapse;
  width: 100%;
}

table, th, td {
   border: 1px solid #ddd;
   padding: 8px;
   text-align: left;
}
</style>

<h2 style="margin:24px 0 8px;">My Approved Products</h2>
<table>
  <?php if (empty($mine)): ?>
    <tr><td colspan="8" class="muted">No products yet.</td></tr>
  <?php else: ?>
    <tr>
      <th>ID</th><th>Name</th><th>Price</th><th>Qty</th><th>Status</th><th>Updated</th><th>Transaction</th><th>QR</th>
    </tr>
    <?php foreach ($mine as $r): ?>
    <?php if ($r['status'] === 'approved'): ?>
      <?php
        // Build viewer URL with product details
        // Make sure QR_VIEWER_BASE in config.php has NO query string
        $viewerUrl = QR_VIEWER_BASE
          . '?id='     . urlencode($r['id'])
          . '&name='   . urlencode($r['name'])
          . '&qty='    . urlencode($r['qty'])
          . '&price='  . urlencode($r['price'])
          . '&status=' . urlencode($r['status']);

        $qrImgUrl  = 'https://api.qrserver.com/v1/create-qr-code/?size=130x130&data='
                   . urlencode($viewerUrl);
      ?>
      <tr data-id="<?= h($r['id']) ?>"
          data-name="<?= h($r['name']) ?>"
          data-price="<?= h($r['price']) ?>"
          data-qty="<?= h($r['qty']) ?>">
        <td>#<?= h($r['id']) ?></td>
        <td><?= h($r['name']) ?></td>
        <td><?= h(number_format($r['price'], 4)) ?></td>
        <td><?= h($r['qty']) ?></td>
        <td>
          <span class="pill <?= $r['status']==='approved'?'ok':'pending' ?>">
            <?= h($r['status']) ?>
          </span>
        </td>
        <td class="muted"><?= h($r['updated_at']) ?></td>
        <td class="muted">
          <a href="https://sepolia.etherscan.io/tx/<?= htmlspecialchars(h($r['ownertx'])) ?>" target="_blank">
            View
          </a>
        </td>
        <td>
          <a data-qr="1" href="<?= h($viewerUrl) ?>" target="_blank">
            <img src="<?= h($qrImgUrl) ?>"
                 alt="QR for product #<?= h($r['id']) ?>"
                 style="width:90px;height:90px;cursor:pointer;">
          </a>
        </td>
      </tr>
    <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</table>

<p class="muted" style="margin-top:10px;">Only your own products are shown here (owner = <?= h($me['username']) ?>).</p>

<h2 style="margin:24px 0 8px;">On-chain Balance</h2>
<p id="chainBalance" class="muted">Loading…</p>
<button type="button" onclick="handleWithdraw()">Withdraw Balance</button>
<pre id="withdrawMsg" class="muted" style="margin-top:8px;white-space:pre-wrap;"></pre>

<?php render_footer(); ?>

<!-- ====== Ethers.js (UMD build) & on-chain glue ====== -->
<script src="https://cdn.jsdelivr.net/npm/ethers@6.13.2/dist/ethers.umd.min.js"></script>
<script src="contract-config.js" defer></script>
<script src="contract.js" defer></script>
<script>
window.addEventListener('load', async () => {
  try {
    const info = await getMyOnChainBalance();
    document.getElementById('chainBalance').textContent =
      info.balEth + " ETH (address " +
      info.addr.slice(0, 6) + "…" + info.addr.slice(-4) + ")";
  } catch (e) {
    document.getElementById('chainBalance').textContent =
      "Failed to load balance: " + (e.shortMessage || e.message || e);
  }
});

async function handleWithdraw() {
  const el = document.getElementById('withdrawMsg');
  el.textContent = "Submitting withdraw transaction…";
  try {
    const hash = await withdrawMyBalance();
    el.textContent =
      "Withdraw submitted. Tx: " + hash + "\nCheck it on Sepolia Etherscan.";
  } catch (e) {
    el.textContent =
      "Withdraw failed: " + (e.shortMessage || e.message || e);
  }
}
</script>
<script defer>
document.addEventListener('DOMContentLoaded', function () {
  const ca = (window.BSTS_CONFIG && window.BSTS_CONFIG.CONTRACT_ADDRESS) || '';
  if (!/^0x[a-fA-F0-9]{40}$/.test(ca)) return; // nothing to do

  document.querySelectorAll('a[data-qr="1"]').forEach(a => {
    try {
      const u = new URL(a.href, location.href);
      if (!u.searchParams.get('ca')) {
        u.searchParams.set('ca', ca);
        a.href = u.toString();
      }

      const img = a.querySelector('img');
      if (img && img.src.includes('api.qrserver.com')) {
        const qr = new URL(img.src, location.href);
        const dataParam = qr.searchParams.get('data') || a.href;
        const data = new URL(dataParam, location.href);
        data.searchParams.set('ca', ca);
        qr.searchParams.set('data', data.toString());
        img.src = qr.toString();
      }
    } catch(e){}
  });
});
</script>

