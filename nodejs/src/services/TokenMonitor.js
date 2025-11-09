const { ethers } = require('ethers');

// ERC20 ABI (Transfer event)
const ERC20_ABI = [
    "event Transfer(address indexed from, address indexed to, uint256 value)",
    "function balanceOf(address account) view returns (uint256)",
    "function decimals() view returns (uint8)"
];

// Initialize provider
const provider = new ethers.JsonRpcProvider(process.env.RPC_URL);

// Token addresses to monitor
const MONITORED_TOKENS = {
    'USDT': '0xdAC17F958D2ee523a2206206994597C13D831ec7',
    'USDC': '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
    'DAI': '0x6B175474E89094C44Da98b954EedeAC495271d0F'
};

async function monitorTokenDeposits() {
    console.log('Starting token deposit monitor...');
    
    // Get all deposit addresses from database
    const depositAddresses = await getDepositAddresses();
    const addressSet = new Set(depositAddresses.map(a => a.address.toLowerCase()));
    
    // Monitor each token
    for (const [tokenName, tokenAddress] of Object.entries(MONITORED_TOKENS)) {
        const tokenContract = new ethers.Contract(tokenAddress, ERC20_ABI, provider);
        
        // Listen for Transfer events
        tokenContract.on('Transfer', async (from, to, amount, event) => {
            const recipient = to.toLowerCase();
            
            // Check if transfer is to one of our deposit addresses
            if (addressSet.has(recipient)) {
                console.log(`\n💰 Deposit detected!`);
                console.log(`Token: ${tokenName}`);
                console.log(`To: ${to}`);
                console.log(`Amount: ${ethers.formatUnits(amount, await tokenContract.decimals())}`);
                console.log(`TxHash: ${event.log.transactionHash}`);
                
                // Add to sweep queue
                await addToSweepQueue({
                    depositAddress: to,
                    tokenAddress: tokenAddress,
                    tokenName: tokenName,
                    amount: amount.toString(),
                    txHash: event.log.transactionHash
                });
                
                // Trigger sweep process
                await processSweep(to, tokenAddress, tokenName);
            }
        });
        
        console.log(`Monitoring ${tokenName} at ${tokenAddress}`);
    }
    
    // Also check for existing balances on startup
    await checkExistingBalances(depositAddresses);
}

async function checkExistingBalances(depositAddresses) {
    console.log('\nChecking existing balances...');
    
    for (const deposit of depositAddresses) {
        for (const [tokenName, tokenAddress] of Object.entries(MONITORED_TOKENS)) {
            const tokenContract = new ethers.Contract(tokenAddress, ERC20_ABI, provider);
            const balance = await tokenContract.balanceOf(deposit.address);
            
            if (balance > 0) {
                console.log(`Found existing balance: ${deposit.address} has ${ethers.formatUnits(balance, await tokenContract.decimals())} ${tokenName}`);
                
                await addToSweepQueue({
                    depositAddress: deposit.address,
                    tokenAddress: tokenAddress,
                    tokenName: tokenName,
                    amount: balance.toString(),
                    txHash: 'existing_balance'
                });
                
                await processSweep(deposit.address, tokenAddress, tokenName);
            }
        }
    }
}

async function getDepositAddresses() {
    // Query database for all deposit addresses
    const result = await db.query('SELECT address FROM deposit_addresses');
    return result.rows;
}

async function addToSweepQueue(sweepData) {
    await db.query(
        `INSERT INTO pending_sweeps (deposit_address, token_address, amount, status) 
         VALUES ($1, $2, $3, 'pending')`,
        [sweepData.depositAddress, sweepData.tokenAddress, sweepData.amount]
    );
}

// Start monitoring
monitorTokenDeposits().catch(console.error);

module.exports = { monitorTokenDeposits };