<?php

class SettlementCalculator {
    private $balances = [];
    private $settlements = [];

    public function calculateBalances($expenses, $group_members) {
        // Initialize balances for all members
        foreach ($group_members as $member) {
            $this->balances[$member['user_id']] = 0;
        }

        // Calculate net balance for each member
        foreach ($expenses as $expense) {
            $paid_by = $expense['paid_by'];
            $this->balances[$paid_by] += $expense['amount'];

            foreach ($expense['splits'] as $split) {
                $this->balances[$split['user_id']] -= $split['amount'];
            }
        }
    }

    public function simplifyDebts() {
        $debtors = [];
        $creditors = [];

        // Separate users into debtors and creditors
        foreach ($this->balances as $user_id => $balance) {
            if ($balance < 0) {
                $debtors[] = ['user_id' => $user_id, 'amount' => abs($balance)];
            } elseif ($balance > 0) {
                $creditors[] = ['user_id' => $user_id, 'amount' => $balance];
            }
        }

        // Sort by amount to optimize transactions
        usort($debtors, fn($a, $b) => $b['amount'] - $a['amount']);
        usort($creditors, fn($a, $b) => $b['amount'] - $a['amount']);

        // Calculate optimal settlements
        while (!empty($debtors) && !empty($creditors)) {
            $debtor = &$debtors[0];
            $creditor = &$creditors[0];

            $amount = min($debtor['amount'], $creditor['amount']);
            
            if ($amount > 0) {
                $this->settlements[] = [
                    'from_user_id' => $debtor['user_id'],
                    'to_user_id' => $creditor['user_id'],
                    'amount' => $amount
                ];
            }

            $debtor['amount'] -= $amount;
            $creditor['amount'] -= $amount;

            if ($debtor['amount'] <= 0) array_shift($debtors);
            if ($creditor['amount'] <= 0) array_shift($creditors);
        }

        return $this->settlements;
    }

    public function getBalances() {
        return $this->balances;
    }
}
