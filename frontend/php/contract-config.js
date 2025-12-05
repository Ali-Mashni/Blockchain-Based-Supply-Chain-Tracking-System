// contract-config.js
// Global config for BSTS dApp

window.BSTS_CONFIG = Object.freeze({
  CONTRACT_ADDRESS: "0x711ca8fBEAeEA2A9c18F794f98f683853e68b630",
  ABI: [
		{
			"inputs": [],
			"stateMutability": "nonpayable",
			"type": "constructor"
		},
		{
			"anonymous": false,
			"inputs": [
				{
					"indexed": true,
					"internalType": "address",
					"name": "account",
					"type": "address"
				},
				{
					"indexed": false,
					"internalType": "uint256",
					"name": "amount",
					"type": "uint256"
				}
			],
			"name": "BalanceWithdrawn",
			"type": "event"
		},
		{
			"anonymous": false,
			"inputs": [
				{
					"indexed": true,
					"internalType": "uint256",
					"name": "id",
					"type": "uint256"
				},
				{
					"indexed": true,
					"internalType": "address",
					"name": "consumer",
					"type": "address"
				},
				{
					"indexed": false,
					"internalType": "uint256",
					"name": "qty",
					"type": "uint256"
				},
				{
					"indexed": false,
					"internalType": "uint256",
					"name": "totalPaid",
					"type": "uint256"
				}
			],
			"name": "ConsumerPaid",
			"type": "event"
		},
		{
			"anonymous": false,
			"inputs": [
				{
					"indexed": true,
					"internalType": "uint256",
					"name": "id",
					"type": "uint256"
				},
				{
					"indexed": true,
					"internalType": "address",
					"name": "owner",
					"type": "address"
				},
				{
					"indexed": false,
					"internalType": "string",
					"name": "metaHash",
					"type": "string"
				},
				{
					"indexed": false,
					"internalType": "uint256",
					"name": "price",
					"type": "uint256"
				},
				{
					"indexed": false,
					"internalType": "uint256",
					"name": "qty",
					"type": "uint256"
				}
			],
			"name": "ProductAdded",
			"type": "event"
		},
		{
			"anonymous": false,
			"inputs": [
				{
					"indexed": true,
					"internalType": "uint256",
					"name": "id",
					"type": "uint256"
				},
				{
					"indexed": true,
					"internalType": "address",
					"name": "supplier",
					"type": "address"
				},
				{
					"indexed": false,
					"internalType": "uint256",
					"name": "qty",
					"type": "uint256"
				},
				{
					"indexed": false,
					"internalType": "uint256",
					"name": "totalPaid",
					"type": "uint256"
				}
			],
			"name": "SupplierPaid",
			"type": "event"
		},
		{
			"inputs": [
				{
					"internalType": "string",
					"name": "metaHash",
					"type": "string"
				},
				{
					"internalType": "uint256",
					"name": "price",
					"type": "uint256"
				},
				{
					"internalType": "uint256",
					"name": "qty",
					"type": "uint256"
				}
			],
			"name": "addProduct",
			"outputs": [],
			"stateMutability": "nonpayable",
			"type": "function"
		},
		{
			"inputs": [],
			"name": "admin",
			"outputs": [
				{
					"internalType": "address",
					"name": "",
					"type": "address"
				}
			],
			"stateMutability": "view",
			"type": "function"
		},
		{
			"inputs": [
				{
					"internalType": "address",
					"name": "",
					"type": "address"
				}
			],
			"name": "balances",
			"outputs": [
				{
					"internalType": "uint256",
					"name": "",
					"type": "uint256"
				}
			],
			"stateMutability": "view",
			"type": "function"
		},
		{
			"inputs": [
				{
					"internalType": "uint256",
					"name": "id",
					"type": "uint256"
				}
			],
			"name": "getProduct",
			"outputs": [
				{
					"components": [
						{
							"internalType": "uint256",
							"name": "id",
							"type": "uint256"
						},
						{
							"internalType": "address",
							"name": "owner",
							"type": "address"
						},
						{
							"internalType": "address",
							"name": "supplier",
							"type": "address"
						},
						{
							"internalType": "address",
							"name": "consumer",
							"type": "address"
						},
						{
							"internalType": "string",
							"name": "ownertx",
							"type": "string"
						},
						{
							"internalType": "string",
							"name": "suppliertx",
							"type": "string"
						},
						{
							"internalType": "string",
							"name": "metaHash",
							"type": "string"
						},
						{
							"internalType": "uint256",
							"name": "price",
							"type": "uint256"
						},
						{
							"internalType": "uint256",
							"name": "qty",
							"type": "uint256"
						},
						{
							"internalType": "bool",
							"name": "approved",
							"type": "bool"
						},
						{
							"internalType": "uint64",
							"name": "createdAt",
							"type": "uint64"
						},
						{
							"internalType": "uint64",
							"name": "updatedAt",
							"type": "uint64"
						}
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
				{
					"internalType": "uint256",
					"name": "",
					"type": "uint256"
				}
			],
			"stateMutability": "view",
			"type": "function"
		},
		{
			"inputs": [
				{
					"internalType": "uint256",
					"name": "id",
					"type": "uint256"
				},
				{
					"internalType": "uint256",
					"name": "qty",
					"type": "uint256"
				}
			],
			"name": "payConsumer",
			"outputs": [],
			"stateMutability": "payable",
			"type": "function"
		},
		{
			"inputs": [
				{
					"internalType": "uint256",
					"name": "productId",
					"type": "uint256"
				},
				{
					"internalType": "uint256",
					"name": "qty",
					"type": "uint256"
				}
			],
			"name": "paySupplier",
			"outputs": [],
			"stateMutability": "payable",
			"type": "function"
		},
		{
			"inputs": [
				{
					"internalType": "uint256",
					"name": "",
					"type": "uint256"
				}
			],
			"name": "products",
			"outputs": [
				{
					"internalType": "uint256",
					"name": "id",
					"type": "uint256"
				},
				{
					"internalType": "address",
					"name": "owner",
					"type": "address"
				},
				{
					"internalType": "address",
					"name": "supplier",
					"type": "address"
				},
				{
					"internalType": "address",
					"name": "consumer",
					"type": "address"
				},
				{
					"internalType": "string",
					"name": "ownertx",
					"type": "string"
				},
				{
					"internalType": "string",
					"name": "suppliertx",
					"type": "string"
				},
				{
					"internalType": "string",
					"name": "metaHash",
					"type": "string"
				},
				{
					"internalType": "uint256",
					"name": "price",
					"type": "uint256"
				},
				{
					"internalType": "uint256",
					"name": "qty",
					"type": "uint256"
				},
				{
					"internalType": "bool",
					"name": "approved",
					"type": "bool"
				},
				{
					"internalType": "uint64",
					"name": "createdAt",
					"type": "uint64"
				},
				{
					"internalType": "uint64",
					"name": "updatedAt",
					"type": "uint64"
				}
			],
			"stateMutability": "view",
			"type": "function"
		},
		{
			"inputs": [
				{
					"internalType": "address",
					"name": "",
					"type": "address"
				}
			],
			"name": "roles",
			"outputs": [
				{
					"internalType": "enum ProductsChain.Role",
					"name": "",
					"type": "uint8"
				}
			],
			"stateMutability": "view",
			"type": "function"
		},
		{
			"inputs": [
				{
					"internalType": "address",
					"name": "user",
					"type": "address"
				},
				{
					"internalType": "enum ProductsChain.Role",
					"name": "role",
					"type": "uint8"
				}
			],
			"name": "setRole",
			"outputs": [],
			"stateMutability": "nonpayable",
			"type": "function"
		},
		{
			"inputs": [],
			"name": "withdrawBalance",
			"outputs": [],
			"stateMutability": "nonpayable",
			"type": "function"
		}
	]
});
