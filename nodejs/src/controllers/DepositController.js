const { generateDepositAddress } = require('../services/DepositAddressGenerator');
const { Pool } = require('pg');

const pool = new Pool({
    connectionString: process.env.DATABASE_URL
});

class DepositController {
    static async generateAddress(req, res) {
        try {
            const { userId } = req.body;

            if (!userId) {
                return res.status(400).json({ error: 'userId is required' });
            }

            const depositInfo = await generateDepositAddress(userId);

            res.json({
                success: true,
                data: depositInfo
            });
        } catch (error) {
            console.error('Error generating deposit address:', error);
            res.status(500).json({ error: 'Failed to generate deposit address' });
        }
    }

    static async getSweepStatus(req, res) {
        try {
            const { address } = req.params;

            const result = await pool.query(
                'SELECT * FROM pending_sweeps WHERE deposit_address = $1 ORDER BY created_at DESC LIMIT 5',
                [address]
            );

            res.json({
                success: true,
                data: result.rows
            });
        } catch (error) {
            console.error('Error fetching sweep status:', error);
            res.status(500).json({ error: 'Failed to fetch sweep status' });
        }
    }
}

module.exports = DepositController;
