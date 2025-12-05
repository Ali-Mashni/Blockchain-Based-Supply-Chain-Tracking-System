<?php
require __DIR__ . '/config.php';
require_role('consumer');

$me = $_SESSION['user'];
render_header("Consumer Dashboard");
?>
<div class="bar">
  <div>
    <h1>Consumer Dashboard</h1>
    <span class="tag"><?= h($me['role']) ?></span>
  </div>
  <div>
    <a href="login.php" style="margin-right:8px;text-decoration:none;"><button class="btn-secondary">Home</button></a>
    <a href="logout.php"><button class="logout">Log out</button></a>
  </div>
</div>

<p class="sub">
  Enter a product ID (later this will come from a QR code) to verify its on-chain details from the
  <b>ProductsChain</b> contract on Sepolia.
</p>

<label>Product ID</label>
<input id="prodId" type="number" min="1" style="max-width:140px">
<button onclick="lookupProduct()">Lookup</button>
<button onclick="buyFromSupplier()">Buy from supplier (on chain)</button>

<pre id="result" class="muted" style="margin-top:12px;white-space:pre-wrap;"></pre>
<pre id="buyResult" class="muted" style="margin-top:8px;white-space:pre-wrap;"></pre>

<?php render_footer(); ?>

<script src="https://cdn.jsdelivr.net/npm/ethers@6.13.2/dist/ethers.umd.min.js"></script>
<script src="contract-config.js"></script>
<script src="contract.js"></script>
<script>
async function getProductFromChain(id) {
  const { contract } = await getSignerAndContract();
  return await contract.getProduct(BigInt(id));
}

async function buyFromSupplier() {
  const id = document.getElementById('prodId').value;
  if (!id) return;

  try {
    const { contract } = await getSignerAndContract();
    const p = await contract.getProduct(BigInt(id));
    const priceWei = p.price;

    const tx = await contract.payConsumer(BigInt(id), { value: priceWei });
    const receipt = await tx.wait();
    const hash = receipt?.hash || tx.hash;

    document.getElementById('buyResult').textContent =
      "Payment successful. Tx: " + hash + "\n(You can view it on Sepolia Etherscan)";
  } catch (e) {
    alert("Consumer payment failed: " + (e.shortMessage || e.message || e));
  }
}

async function lookupProduct(){
  const id = document.getElementById('prodId').value;
  if (!id) return;

  try {
    const p = await getProductFromChain(id);
    const lines = [
      'On-chain ID: ' + p.id,
      'Owner:      ' + p.owner,
      'Supplier:   ' + p.supplier,
      'Consumer:   ' + p.consumer,
      'Meta hash:  ' + p.metaHash,
      'Price (wei): ' + p.price,
      'Qty:        ' + p.qty,
      'Approved:   ' + p.approved,
      'CreatedAt:  ' + p.createdAt,
      'UpdatedAt:  ' + p.updatedAt
    ];
    document.getElementById('result').textContent = lines.join('\n');
  } catch (e) {
    alert('Failed to fetch product: ' + (e.shortMessage || e.message || e));
  }
}
</script>
