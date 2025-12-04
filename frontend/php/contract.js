// === CONFIG: fill with your deployed contract data ===

const CONTRACT_ADDRESS = ""; //YOUR SMART CONTRACT ADDRESS GOES HERE

const ABI = [ ]; //YOUR ABI CONTENTS GO HERE

function toWei(ethString){
  return ethers.parseUnits(String(ethString), 18);
}

async function getSignerAndContract(){
  if (!window.ethereum) throw new Error("MetaMask not found");
  await ethereum.request({ method: 'eth_requestAccounts' });
  const provider = new ethers.BrowserProvider(window.ethereum);
  const signer = await provider.getSigner();
  const contract = new ethers.Contract(CONTRACT_ADDRESS, ABI, signer);
  return { signer, contract };
}

// Approve handler: call chain → on success, submit form with tx hash
async function handleApproveOnChain(row) {
  const id   = row.dataset.id;
  const name = row.dataset.name;
  const price= row.dataset.price; // displayed currency; adapt if you keep ETH elsewhere
  const qty  = row.dataset.qty;

  // Simple meta payload: include local product id + name (or use IPFS and put CID here)
  const metaHash = `local:${id}|${name}`;

  const { contract } = await getSignerAndContract();
  // If your on-chain price is in wei, convert (here we assume your local price is ETH)
  // If your local price is *not* ETH, replace with the correct value that the contract expects:
  const tx = await contract.addProduct(metaHash, toWei(price), BigInt(qty));
  const receipt = await tx.wait();

  return receipt?.hash || tx.hash; // v6 returns tx.hash; receipt.hash is the same
}

function wireApproveButtons(){
  const buttons = document.querySelectorAll('.btn-onchain-approve');
  buttons.forEach(btn => {
    btn.addEventListener('click', async () => {
      const row = btn.closest('tr');
      const form = row.querySelector('form.approve-form');
      const txInput = form.querySelector('input[name="txhash"]');

      btn.disabled = true;
      btn.textContent = 'Approving…';
      

      try {
        const txhash = await handleApproveOnChain(row);
        txInput.value = txhash || '';
        // After chain success, submit the PHP form to mark approved + show tx
        form.submit();
      } catch (err) {
        console.error(err);
        alert('On-chain approval failed: ' + (err?.shortMessage || err?.message || err));
        btn.disabled = false;
        btn.textContent = 'Approve';
      }
    });
  });
}

window.addEventListener('load', wireApproveButtons);