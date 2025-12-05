// === CONFIG: fill with your deployed contract data ===

// Expect CONTRACT_ADDRESS and ABI to be provided by contract-config.js
const { CONTRACT_ADDRESS, ABI } = window.BSTS_CONFIG || {};

if (!CONTRACT_ADDRESS || !ABI) {
  console.error("BSTS_CONFIG is missing CONTRACT_ADDRESS or ABI.");
}

function toWei(ethString) {
  return ethers.parseUnits(String(ethString), 18);
}

async function getSignerAndContract() {
  if (!window.ethereum) throw new Error("MetaMask not found");
  await ethereum.request({ method: "eth_requestAccounts" });

  const provider = new ethers.BrowserProvider(window.ethereum);
  const signer = await provider.getSigner();
  const contract = new ethers.Contract(CONTRACT_ADDRESS, ABI, signer);
  return { signer, contract };
}

// ---------- Producer approve: addProduct(...) ----------
async function handleApproveOnChain(row) {
  const id   = row.dataset.id;
  const name = row.dataset.name;
  const price = row.dataset.price; // ETH string
  const qty   = row.dataset.qty;   // string

  const metaHash = `local:${id}|${name}`;

  const { contract } = await getSignerAndContract();
  const tx = await contract.addProduct(metaHash, toWei(price), BigInt(qty));
  const receipt = await tx.wait();
  return receipt?.hash || tx.hash;
}

function wireApproveButtons() {
  const buttons = document.querySelectorAll(".btn-onchain-approve");
  buttons.forEach((btn) => {
    btn.addEventListener("click", async () => {
      const row = btn.closest("tr");
      const form = row.querySelector("form.approve-form");
      const txInput = form.querySelector('input[name="txhash"]');

      btn.disabled = true;
      btn.textContent = "Approving…";

      try {
        const txhash = await handleApproveOnChain(row);
        txInput.value = txhash || "";
        form.submit();
      } catch (err) {
        console.error(err);
        alert(
          "On-chain approval failed: " + (err?.shortMessage || err?.message || err)
        );
        btn.disabled = false;
        btn.textContent = "Approve";
      }
    });
  });
}
// ---------- Consumer pays supplier (qty-aware) ----------
async function handleConsumerBuyOnChain(row) {
  // On-chain product ID
  const onchainId = BigInt(row.dataset.rootId || row.dataset.id);

  const priceEthStr = row.dataset.price;
  const qtyInput = row.querySelector('input[name="qty"]');
  const qty = BigInt(qtyInput.value);
  if (qty <= 0n) {
    throw new Error("Quantity must be > 0");
  }

  const priceWei = ethers.parseUnits(priceEthStr, 18); // per-unit
  const totalWei = priceWei * qty;

  const { contract } = await getSignerAndContract();
  const tx = await contract.payConsumer(onchainId, qty, { value: totalWei });
  const receipt = await tx.wait();
  return receipt?.hash || tx.hash;
}


function wireConsumerBuyButtons() {
  const buttons = document.querySelectorAll(".btn-consumer-buy");
  buttons.forEach((btn) => {
    btn.addEventListener("click", async () => {
      const row = btn.closest("tr");
      const form = row.querySelector("form.consumer-buy-form");
      const txInput = form.querySelector('input[name="txhash"]');

      btn.disabled = true;
      btn.textContent = "Buying…";

      try {
        const txhash = await handleConsumerBuyOnChain(row);
        txInput.value = txhash || "";
        form.submit(); // PHP: deduct qty + save txhash
      } catch (err) {
        console.error(err);
        alert(
          "On-chain consumer payment failed: " +
          (err?.shortMessage || err?.message || err)
        );
        btn.disabled = false;
        btn.textContent = "Buy on chain";
      }
    });
  });
}

// ---------- Supplier pays producer (qty-aware) ----------
async function handleSupplierBuyOnChain(row) {
  const id = BigInt(row.dataset.id);       // product id
  const priceEthStr = row.dataset.price;   // per-unit price in ETH

  const qtyInput = row.querySelector('input[name="qty"]');
  const qty = BigInt(qtyInput.value);

  if (qty <= 0n) {
    throw new Error("Quantity must be > 0");
  }

  const priceWei = ethers.parseUnits(priceEthStr, 18); // per-unit wei
  const totalWei = priceWei * qty;

  const { contract } = await getSignerAndContract();
  const tx = await contract.paySupplier(id, qty, { value: totalWei });
  const receipt = await tx.wait();
  return receipt?.hash || tx.hash;
}

function wireSupplierBuyButtons() {
  const buttons = document.querySelectorAll(".btn-onchain-buy");
  buttons.forEach((btn) => {
    btn.addEventListener("click", async () => {
      const row = btn.closest("tr");
      const form = row.querySelector("form.supplier-buy-form");
      const txInput = form.querySelector('input[name="txhash"]');

      btn.disabled = true;
      btn.textContent = "Buying…";

      try {
        const txhash = await handleSupplierBuyOnChain(row);
        txInput.value = txhash || "";
        form.submit(); // PHP: deduct qty + save suppliertx
      } catch (err) {
        console.error(err);
        alert(
          "On-chain supplier payment failed: " + (err?.shortMessage || err?.message || err)
        );
        btn.disabled = false;
        btn.textContent = "Buy on chain";
      }
    });
  });
}

// ---------- Balance helpers ----------
async function getMyOnChainBalance() {
  const { signer, contract } = await getSignerAndContract();
  const addr = await signer.getAddress();
  const balWei = await contract.balances(addr);
  const balEth = ethers.formatEther(balWei);
  return { addr, balWei, balEth };
}

async function withdrawMyBalance() {
  const { contract } = await getSignerAndContract();
  const tx = await contract.withdrawBalance();
  const receipt = await tx.wait();
  return receipt?.hash || tx.hash;
}

// Wire on load
window.addEventListener("load", () => {
  wireApproveButtons();
  wireSupplierBuyButtons();
  wireConsumerBuyButtons(); 
});
