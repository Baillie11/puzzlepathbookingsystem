<?php
declare(strict_types=1);
// Called only by a future provider adapter AFTER signature verification and status reconciliation.
// The HTTP route deliberately remains disabled until that provider implementation exists.
interface BookingNotificationVerifier {
    public function verify(string $body,array $headers): array;
}
final class ConversionLedger {
    public function __construct(private PDO $db) {
        $db->exec('CREATE TABLE IF NOT EXISTS conversions (provider TEXT NOT NULL, reference TEXT NOT NULL, status TEXT NOT NULL, campaign TEXT NOT NULL, amount_cents INTEGER, at TEXT NOT NULL, PRIMARY KEY(provider,reference,status))');
    }
    public function accept(BookingNotificationVerifier $verifier,string $body,array $headers): bool {
        $event=$verifier->verify($body,$headers);
        if (!in_array($event['status']??'', ['booking_completed','payment_confirmed'],true) || empty($event['reference']) || empty($event['provider']) || empty($event['campaign']) || !is_int($event['amount_cents']??null) || $event['amount_cents']<0) throw new RuntimeException('Invalid confirmed booking event.');
        $statement=$this->db->prepare('INSERT OR IGNORE INTO conversions VALUES (?,?,?,?,?,?)');
        $statement->execute([$event['provider'],$event['reference'],$event['status'],$event['campaign'],$event['amount_cents'],gmdate('c')]);
        return $statement->rowCount()===1;
    }
}
