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
    public function fetchByDate(int $tenantId, string $date, ?int $providerId = null): array {
        if ($providerId !== null) {
            $stmt = $this->db->prepare(
                'SELECT id, patient_id, provider_id,
                        appointment_date, appointment_time,
                        status, notes, is_cancelled
                 FROM   appointments
                 WHERE  tenant_id        = :tenant_id
                   AND  appointment_date = :date
                   AND  provider_id      = :provider_id
                   AND  is_cancelled     = 0
                 ORDER BY appointment_time ASC'
            );
            $stmt->execute([
                'tenant_id'   => $tenantId,
                'date'        => $date,
                'provider_id' => $providerId,
            ]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT id, patient_id, provider_id,
                        appointment_date, appointment_time,
                        status, notes, is_cancelled
                 FROM   appointments
                 WHERE  tenant_id        = :tenant_id
                   AND  appointment_date = :date
                   AND  is_cancelled     = 0
                 ORDER BY appointment_time ASC'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'date'      => $date,
            ]);
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
    public function fetchByRange(int $tenantId, string $startDate, string $endDate, ?int $providerId = null): array {
        if ($providerId !== null) {
            $stmt = $this->db->prepare(
                'SELECT id, patient_id, provider_id,
                        appointment_date, appointment_time,
                        status, notes, is_cancelled
                 FROM   appointments
                 WHERE  tenant_id        = :tenant_id
                   AND  appointment_date BETWEEN :start_date AND :end_date
                   AND  provider_id      = :provider_id
                   AND  is_cancelled     = 0
                 ORDER BY appointment_date ASC, appointment_time ASC'
            );
            $stmt->execute([
                'tenant_id'   => $tenantId,
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'provider_id' => $providerId,
            ]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT id, patient_id, provider_id,
                        appointment_date, appointment_time,
                        status, notes, is_cancelled
                 FROM   appointments
                 WHERE  tenant_id        = :tenant_id
                   AND  appointment_date BETWEEN :start_date AND :end_date
                   AND  is_cancelled     = 0
                 ORDER BY appointment_date ASC, appointment_time ASC'
            );
            $stmt->execute([
                'tenant_id'  => $tenantId,
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
    public function fetchTooltip(int $id, int $tenantId): ?array {
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
               AND  a.tenant_id = :tenant_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
