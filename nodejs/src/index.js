require('dotenv').config();
const express = require('express');
const { monitorTokenDeposits } = require('./services/TokenMonitor');
const apiRoutes = require('./routes/api');

const app = express();
app.use(express.json());

// Mount API routes
app.use('/api', apiRoutes);

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

// Start the server
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
});

// Start monitoring token deposits
monitorTokenDeposits().catch(err => {
    console.error('Fatal error in token monitor:', err);
    process.exit(1);
});

// Graceful shutdown
process.on('SIGINT', () => {
    console.log('\nShutting down gracefully...');
    process.exit(0);
});
