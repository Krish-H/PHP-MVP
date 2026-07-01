<?php

namespace App\Repositories;

use App\Config\Database;

/**
 * CalendarRepository
 *
 * Read-only queries for the calendar view.
 * All writes go through AppointmentRepository.
 *
 * Role-based filtering (provider_id scope) is applied by CalendarService.
 */
class CalendarRepository {

    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // ----------------------------------------------------------------
    // Fetch by single date
    // ----------------------------------------------------------------

    /**
     * Get all appointments for a tenant on a specific date.
     * If $providerId is given, scope to that provider only (Doctor role).
     *
     * @param  int         $tenantId
     * @param  string      $date        YYYY-MM-DD
     * @param  int|null    $providerId  null = all providers
     * @return array
     */
    public function fetchByDate(string $date, ?int $providerId = null): array {
        if ($providerId !== null) {
            $stmt = $this->db->prepare(
                'SELECT a.id, a.patient_id, a.provider_id,
                        a.appointment_date, a.appointment_time,
                        a.status, a.notes, a.is_cancelled,
                        p.name AS patient_name_enc,
                        u.name AS provider_name
                 FROM   appointments a
                 LEFT JOIN patients p ON p.id = a.patient_id AND p.is_deleted = 0
                 LEFT JOIN users u ON u.id = a.provider_id
                 WHERE  a.appointment_date = :date
                   AND  a.provider_id      = :provider_id
                   AND  a.is_cancelled     = 0
                 ORDER BY a.appointment_time ASC'
            );
            $stmt->execute([
                'date'        => $date,
                'provider_id' => $providerId,
            ]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT a.id, a.patient_id, a.provider_id,
                        a.appointment_date, a.appointment_time,
                        a.status, a.notes, a.is_cancelled,
                        p.name AS patient_name_enc,
                        u.name AS provider_name
                 FROM   appointments a
                 LEFT JOIN patients p ON p.id = a.patient_id AND p.is_deleted = 0
                 LEFT JOIN users u ON u.id = a.provider_id
                 WHERE  a.appointment_date = :date
                   AND  a.is_cancelled     = 0
                 ORDER BY a.appointment_time ASC'
            );
            $stmt->execute(['date' => $date]);
        }

        return $stmt->fetchAll();
    }

    // ----------------------------------------------------------------
    // Fetch by date range
    // ----------------------------------------------------------------

    /**
     * Get all appointments within a date range.
     * If $providerId is given, scope to that provider only.
     *
     * @param  int      $tenantId
     * @param  string   $startDate  YYYY-MM-DD
     * @param  string   $endDate    YYYY-MM-DD
     * @param  int|null $providerId
     * @return array
     */
    public function fetchByRange(string $startDate, string $endDate, ?int $providerId = null): array {
        if ($providerId !== null) {
            $stmt = $this->db->prepare(
                'SELECT a.id, a.patient_id, a.provider_id,
                        a.appointment_date, a.appointment_time,
                        a.status, a.notes, a.is_cancelled,
                        p.name AS patient_name_enc,
                        u.name AS provider_name
                 FROM   appointments a
                 LEFT JOIN patients p ON p.id = a.patient_id AND p.is_deleted = 0
                 LEFT JOIN users u ON u.id = a.provider_id
                 WHERE  a.appointment_date BETWEEN :start_date AND :end_date
                   AND  a.provider_id      = :provider_id
                   AND  a.is_cancelled     = 0
                 ORDER BY a.appointment_date ASC, a.appointment_time ASC'
            );
            $stmt->execute([
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'provider_id' => $providerId,
            ]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT a.id, a.patient_id, a.provider_id,
                        a.appointment_date, a.appointment_time,
                        a.status, a.notes, a.is_cancelled,
                        p.name AS patient_name_enc,
                        u.name AS provider_name
                 FROM   appointments a
                 LEFT JOIN patients p ON p.id = a.patient_id AND p.is_deleted = 0
                 LEFT JOIN users u ON u.id = a.provider_id
                 WHERE  a.appointment_date BETWEEN :start_date AND :end_date
                   AND  a.is_cancelled     = 0
                 ORDER BY a.appointment_date ASC, a.appointment_time ASC'
            );
            $stmt->execute([
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ]);
        }

        return $stmt->fetchAll();
    }

    // ----------------------------------------------------------------
    // Tooltip details
    // ----------------------------------------------------------------

    /**
     * Fetch full appointment details for tooltip.
     * Joins with patients to pull encrypted name.
     *
     * @param  int $id
     * @param  int $tenantId
     * @return array|null
     */
    public function fetchTooltip(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT a.id, a.patient_id, a.provider_id,
                    a.appointment_date, a.appointment_time,
                    a.status, a.notes, a.is_cancelled,
                    p.name  AS patient_name_enc,
                    p.phone AS patient_phone_enc
             FROM   appointments a
             LEFT JOIN patients p
                    ON p.id = a.patient_id AND p.is_deleted = 0
             WHERE  a.id        = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
