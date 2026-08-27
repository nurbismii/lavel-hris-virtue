<?php

namespace App\Services\Roster;

use App\Models\RosterScheduleHistory;

class RosterHistoryRemarkParser
{
    private const PERIODS = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V'];

    public function parse(?string $rawRemark, int $periodNumber): array
    {
        $rawRemark = trim((string) $rawRemark);

        if ($rawRemark === '') {
            return $this->result(RosterScheduleHistory::CLASSIFICATION_PLANNED, null, false);
        }

        $segment = $this->segmentForPeriod($rawRemark, $periodNumber);

        if (!$segment) {
            if ($this->isNotApplicable(mb_strtoupper(str_replace('_', ' ', $rawRemark)))) {
                return $this->result(
                    RosterScheduleHistory::CLASSIFICATION_NOT_APPLICABLE,
                    mb_substr($rawRemark, 0, 2000),
                    false
                );
            }

            return $this->result(RosterScheduleHistory::CLASSIFICATION_PLANNED, null, false);
        }

        $normalized = mb_strtoupper(str_replace('_', ' ', $segment));
        $hasIncentive = preg_match('/\bINSENTIF\b/u', $normalized) === 1;
        $hasLeave = preg_match('/\bCUTI\b/u', $normalized) === 1;

        if ($hasIncentive && $hasLeave) {
            return $this->result(RosterScheduleHistory::CLASSIFICATION_NEED_REVIEW, $segment, true);
        }

        if ($hasIncentive) {
            return $this->result(RosterScheduleHistory::CLASSIFICATION_INSENTIF, $segment, false);
        }

        if ($hasLeave) {
            return $this->result(RosterScheduleHistory::CLASSIFICATION_CUTI, $segment, false);
        }

        if ($this->isNotApplicable($normalized)) {
            return $this->result(RosterScheduleHistory::CLASSIFICATION_NOT_APPLICABLE, $segment, false);
        }

        if (preg_match('/\bAMBIL\b|\bDIAMBIL\b|\bGABUNG\b|\bMUNDUR\b|\bMAJU\b/u', $normalized) === 1) {
            return $this->result(RosterScheduleHistory::CLASSIFICATION_NEED_REVIEW, $segment, true);
        }

        return $this->result(RosterScheduleHistory::CLASSIFICATION_PLANNED, $segment, false);
    }

    private function isNotApplicable(string $normalized): bool
    {
        return preg_match('/BUKAN\s+ROSTER|TIDAK\s+MENGIKUT\s+ROSTER|PROMOSI/u', $normalized) === 1;
    }

    private function segmentForPeriod(string $remark, int $periodNumber): ?string
    {
        $target = self::PERIODS[$periodNumber] ?? null;

        if (!$target) {
            return null;
        }

        preg_match_all(
            '/(?<![A-Z0-9])(IV|III|II|I|V)(?=\s*(?:[\.,:&\-]|$))/iu',
            $remark,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        if (empty($matches[1])) {
            return null;
        }

        foreach ($matches[1] as $index => $match) {
            if (mb_strtoupper($match[0]) !== $target) {
                continue;
            }

            $start = $match[1];
            $end = isset($matches[1][$index + 1]) ? $matches[1][$index + 1][1] : strlen($remark);

            while (isset($matches[1][$index + 1])) {
                $currentEnd = $match[1] + strlen($match[0]);
                $between = substr($remark, $currentEnd, $matches[1][$index + 1][1] - $currentEnd);

                if (preg_match('/^[\s\.,:&\-\/]*$/u', $between) !== 1) {
                    break;
                }

                $nextIndex = $index + 1;
                $end = isset($matches[1][$nextIndex + 1]) ? $matches[1][$nextIndex + 1][1] : strlen($remark);
                break;
            }

            $segment = trim(substr($remark, $start, $end - $start), " \t\n\r\0\x0B_-.,");

            return $segment !== '' ? mb_substr($segment, 0, 2000) : null;
        }

        return null;
    }

    private function result(string $classification, ?string $segment, bool $needsReview): array
    {
        return [
            'classification' => $classification,
            'review_status' => $needsReview
                ? RosterScheduleHistory::REVIEW_PENDING
                : RosterScheduleHistory::REVIEW_NOT_REQUIRED,
            'remark_segment' => $segment,
        ];
    }
}
