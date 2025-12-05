// SPDX-License-Identifier: GPL-3.0
pragma solidity >=0.8.2 <0.9.0;

contract ProductsChain {
    struct Product {
        uint256 id;
        address owner;      // current owner
        address supplier;   // last supplier
        address consumer;   // last consumer
        string  ownertx;    // kept empty on chain; used off-chain in PHP
        string  suppliertx; // kept empty on chain; used off-chain in PHP
        string  metaHash;
        uint256 price;      // price per unit in WEI
        uint256 qty;        // quantity
        bool    approved;
        uint64  createdAt;
        uint64  updatedAt;
    }

    enum Role { None, Producer, Supplier, Consumer }

    address public admin;
    uint256 public nextProductId;
    mapping(uint256 => Product) public products;
    mapping(address => uint256) public balances;
    mapping(address => Role) public roles;

    event ProductAdded(
        uint256 indexed id,
        address indexed owner,
        string metaHash,
        uint256 price,
        uint256 qty
    );
    event SupplierPaid(uint256 indexed id, address indexed supplier, uint256 value);
    event ConsumerPaid(uint256 indexed id, address indexed consumer, uint256 value);
    event BalanceWithdrawn(address indexed account, uint256 amount);

    modifier onlyAdmin() {
        require(msg.sender == admin, "not admin");
        _;
    }

    constructor() {
        admin = msg.sender;
    }

    // ---- Roles: set once in Remix for demo accounts ----
    function setRole(address user, Role role) external onlyAdmin {
        roles[user] = role;
    }

    // ---------- Producer: register product ----------
    function addProduct(
        string calldata metaHash,
        uint256 price,
        uint256 qty
    ) external {
        require(roles[msg.sender] == Role.Producer, "not producer");
        require(qty > 0, "qty=0");
        require(price > 0, "price=0");

        uint256 id = ++nextProductId;

        products[id] = Product({
            id: id,
            owner: msg.sender,
            supplier: address(0),
            consumer: address(0),
            ownertx: "",
            suppliertx: "",
            metaHash: metaHash,
            price: price,
            qty: qty,
            approved: true,
            createdAt: uint64(block.timestamp),
            updatedAt: uint64(block.timestamp)
        });

        emit ProductAdded(id, msg.sender, metaHash, price, qty);
    }

    // ---------- Supplier: pay producer on chain ----------
    // For simplicity: pays for 1 unit at price (you can think qty as batch size off-chain)
    function paySupplier(uint256 id) external payable {
        Product storage p = products[id];
        require(p.id != 0 && p.approved, "not found/approved");
        require(roles[msg.sender] == Role.Supplier, "not supplier");
        require(msg.sender != p.owner, "already owner");
        require(msg.value == p.price, "wrong amount");

        // credit producer
        balances[p.owner] += msg.value;
        p.supplier = msg.sender;
        p.owner = msg.sender;
        p.updatedAt = uint64(block.timestamp);

        emit SupplierPaid(id, msg.sender, msg.value);
    }

    // ---------- Consumer: pay supplier on chain ----------
    function payConsumer(uint256 id) external payable {
        Product storage p = products[id];
        require(p.id != 0 && p.approved, "not found/approved");
        require(roles[msg.sender] == Role.Consumer, "not consumer");
        require(p.supplier != address(0), "no supplier yet");
        require(msg.sender != p.supplier, "already owner");
        require(msg.value == p.price, "wrong amount");

        balances[p.supplier] += msg.value;
        p.consumer = msg.sender;
        p.owner = msg.sender;
        p.updatedAt = uint64(block.timestamp);

        emit ConsumerPaid(id, msg.sender, msg.value);
    }

    // ---------- Withdraw accumulated balances ----------
    function withdrawBalance() external {
        uint256 amount = balances[msg.sender];
        require(amount > 0, "no balance");
        balances[msg.sender] = 0;
        (bool ok, ) = msg.sender.call{value: amount}("");
        require(ok, "transfer failed");
        emit BalanceWithdrawn(msg.sender, amount);
    }

    // ---------- Views ----------
    function getProduct(uint256 id) external view returns (Product memory) {
        return products[id];
    }
}
