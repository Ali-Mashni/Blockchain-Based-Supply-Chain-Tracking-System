// contract-config.js
// Global config for BSTS dApp

window.BSTS_CONFIG = Object.freeze({
  CONTRACT_ADDRESS: "0x5706Ef3aCF671B42CF34AFa18F22D359C4DA8c8d",
  ABI: [
    {
      "anonymous": false,
      "inputs": [
        { "indexed": true,  "internalType": "uint256", "name": "id",     "type": "uint256" },
        { "indexed": true,  "internalType": "address", "name": "owner",  "type": "address" },
        { "indexed": false, "internalType": "string",  "name": "metaHash","type": "string" },
        { "indexed": false, "internalType": "uint256", "name": "price",  "type": "uint256" },
        { "indexed": false, "internalType": "uint256", "name": "qty",    "type": "uint256" }
      ],
      "name": "ProductAdded",
      "type": "event"
    },
    {
      "inputs": [
        { "internalType": "string",  "name": "metaHash", "type": "string" },
        { "internalType": "uint256", "name": "price",    "type": "uint256" },
        { "internalType": "uint256", "name": "qty",      "type": "uint256" }
      ],
      "name": "addProduct",
      "outputs": [],
      "stateMutability": "nonpayable",
      "type": "function"
    },
    {
      "inputs": [
        { "internalType": "uint256", "name": "id", "type": "uint256" }
      ],
      "name": "getProduct",
      "outputs": [
        {
          "components": [
            { "internalType": "uint256", "name": "id",        "type": "uint256" },
            { "internalType": "address", "name": "owner",     "type": "address" },
            { "internalType": "address", "name": "supplier",  "type": "address" },
            { "internalType": "address", "name": "consumer",  "type": "address" },
            { "internalType": "string",  "name": "ownertx",   "type": "string" },
            { "internalType": "string",  "name": "suppliertx","type": "string" },
            { "internalType": "string",  "name": "metaHash",  "type": "string" },
            { "internalType": "uint256", "name": "price",     "type": "uint256" },
            { "internalType": "uint256", "name": "qty",       "type": "uint256" },
            { "internalType": "bool",    "name": "approved",  "type": "bool" },
            { "internalType": "uint64",  "name": "createdAt", "type": "uint64" },
            { "internalType": "uint64",  "name": "updatedAt", "type": "uint64" }
          ],
          "internalType": "struct ProductsChain.Product",
          "name": "",
          "type": "tuple"
        }
      ],
      "stateMutability": "view",
      "type": "function"
    },
    {
      "inputs": [],
      "name": "nextProductId",
      "outputs": [
        { "internalType": "uint256", "name": "", "type": "uint256" }
      ],
      "stateMutability": "view",
      "type": "function"
    },
    {
      "inputs": [
        { "internalType": "uint256", "name": "", "type": "uint256" }
      ],
      "name": "products",
      "outputs": [
        { "internalType": "uint256", "name": "id",        "type": "uint256" },
        { "internalType": "address", "name": "owner",     "type": "address" },
        { "internalType": "address", "name": "supplier",  "type": "address" },
        { "internalType": "address", "name": "consumer",  "type": "address" },
        { "internalType": "string",  "name": "ownertx",   "type": "string" },
        { "internalType": "string",  "name": "suppliertx","type": "string" },
        { "internalType": "string",  "name": "metaHash",  "type": "string" },
        { "internalType": "uint256", "name": "price",     "type": "uint256" },
        { "internalType": "uint256", "name": "qty",       "type": "uint256" },
        { "internalType": "bool",    "name": "approved",  "type": "bool" },
        { "internalType": "uint64",  "name": "createdAt", "type": "uint64" },
        { "internalType": "uint64",  "name": "updatedAt", "type": "uint64" }
      ],
      "stateMutability": "view",
      "type": "function"
    }
  ]
});
