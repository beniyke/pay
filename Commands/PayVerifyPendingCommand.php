<?php

declare(strict_types=1);

/**
 * Anchor Framework - Pay Package
 *
 * Verify Pending Payments Command
 *
 * Reconciliation fallback that checks pending payments with gateways
 * and triggers appropriate events for successful payments.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Commands;

use Helpers\DateTimeHelper;
use Pay\Enums\Status;
use Pay\Events\PaymentSuccessfulEvent;
use Pay\Models\PaymentTransaction;
use Pay\PayManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class PayVerifyPendingCommand extends Command
{
    protected static $defaultName = 'pay:verify-pending';

    protected function configure(): void
    {
        $this
            ->setName('pay:verify-pending')
            ->setDescription('Verify pending payments with payment gateways and trigger events for successful ones')
            ->setHelp(
                'This command queries all pending payment transactions and verifies their status ' .
                    'with the respective payment gateways. Successful payments will trigger the ' .
                    'PaymentSuccessfulEvent event, enabling wallet funding and other integrations.'
            )
            ->addOption(
                'hours',
                'H',
                InputOption::VALUE_OPTIONAL,
                'Only check payments created within the last N hours',
                24
            )
            ->addOption(
                'driver',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Filter by specific payment driver (e.g., paystack, stripe)',
                null
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of transactions to process',
                100
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be verified without making changes'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hours = (int) $input->getOption('hours');
        $driver = $input->getOption('driver');
        $limit = (int) $input->getOption('limit');
        $dryRun = $input->getOption('dry-run');

        $output->writeln('<info>Pay: Verify Pending Payments</info>');
        $output->writeln('');

        if ($dryRun) {
            $output->writeln('<comment>[DRY RUN] No changes will be made.</comment>');
            $output->writeln('');
        }

        // Calculate cutoff time
        $cutoff = DateTimeHelper::now()->subHours($hours)->toDateTimeString();

        // Query pending transactions
        $query = PaymentTransaction::query()
            ->pending()
            ->where('created_at', '>=', $cutoff)
            ->limit($limit)
            ->orderBy('created_at', 'ASC');

        if ($driver) {
            $query->where('driver', $driver);
        }

        $transactions = $query->get();

        if ($transactions->count() === 0) {
            $output->writeln('<comment>No pending transactions found.</comment>');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            'Found <info>%d</info> pending transaction(s) from the last %d hour(s).',
            $transactions->count(),
            $hours
        ));
        $output->writeln('');

        $payManager = resolve(PayManager::class);
        $results = [
            'verified' => 0,
            'successful' => 0,
            'failed' => 0,
            'still_pending' => 0,
            'errors' => 0,
        ];

        $detailedResults = [];

        foreach ($transactions as $transaction) {
            $reference = $transaction->reference;
            $txDriver = $transaction->driver;

            try {
                if ($dryRun) {
                    $detailedResults[] = [
                        'reference' => $reference,
                        'driver' => $txDriver,
                        'amount' => $transaction->amount,
                        'result' => '<comment>SKIPPED (dry-run)</comment>',
                    ];
                    continue;
                }

                // Verify with the payment gateway
                $response = $payManager->driver($txDriver)->verify($reference);
                $results['verified']++;

                // Check verification result
                if ($response->success) {
                    // Update transaction status
                    $transaction->status = Status::SUCCESS;
                    $transaction->save();

                    // Dispatch PaymentSuccessfulEvent event
                    event(new PaymentSuccessfulEvent($transaction, $response->data ?? []));

                    $results['successful']++;
                    $detailedResults[] = [
                        'reference' => $reference,
                        'driver' => $txDriver,
                        'amount' => $transaction->amount,
                        'result' => '<info>SUCCESS</info> (event dispatched)',
                    ];
                } elseif (isset($response->status) && $response->status === 'failed') {
                    // Mark as failed
                    $transaction->status = Status::FAILED;
                    $transaction->save();

                    $results['failed']++;
                    $detailedResults[] = [
                        'reference' => $reference,
                        'driver' => $txDriver,
                        'amount' => $transaction->amount,
                        'result' => '<error>FAILED</error>',
                    ];
                } else {
                    // Still pending at gateway
                    $results['still_pending']++;
                    $detailedResults[] = [
                        'reference' => $reference,
                        'driver' => $txDriver,
                        'amount' => $transaction->amount,
                        'result' => '<comment>STILL PENDING</comment>',
                    ];
                }
            } catch (Throwable $e) {
                $results['errors']++;
                $detailedResults[] = [
                    'reference' => $reference,
                    'driver' => $txDriver,
                    'amount' => $transaction->amount,
                    'result' => '<error>ERROR: ' . $e->getMessage() . '</error>',
                ];
            }
        }

        // Display results table
        $table = new Table($output);
        $table->setHeaders(['Reference', 'Driver', 'Amount', 'Result']);
        $table->setRows($detailedResults);
        $table->render();

        $output->writeln('');
        $output->writeln('<info>Summary:</info>');
        $output->writeln(sprintf('  Verified:      %d', $results['verified']));
        $output->writeln(sprintf('  Successful:    <info>%d</info> (events dispatched)', $results['successful']));
        $output->writeln(sprintf('  Failed:        <error>%d</error>', $results['failed']));
        $output->writeln(sprintf('  Still Pending: <comment>%d</comment>', $results['still_pending']));
        $output->writeln(sprintf('  Errors:        <error>%d</error>', $results['errors']));

        return Command::SUCCESS;
    }
}
