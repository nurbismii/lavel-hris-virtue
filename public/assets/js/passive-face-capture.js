/* Coordinates capture only; the server/API decides whether liveness passes. */
class PassiveFaceCapture {
    constructor(durationMs) {
        this.durationMs = durationMs;
        this.reset();
    }

    reset() {
        this.startedAt = null;
        this.lastSeenAt = null;
        this.frameCount = 0;
        this.lastFrameAt = null;
    }

    observe(valid, now) {
        if (!valid || (this.lastSeenAt !== null && now - this.lastSeenAt > 2000)) {
            this.reset();
        }
        if (!valid) { return { frameIndex: null, progress: 0, complete: false }; }
        this.lastSeenAt = now;
        if (this.startedAt === null) { this.startedAt = now; }
        const elapsed = now - this.startedAt;
        const frameIndex = this.frameCount < 3 && elapsed >= this.frameCount * this.durationMs / 2
            && (this.lastFrameAt === null || now - this.lastFrameAt >= this.durationMs / 4)
            ? this.frameCount++ : null;
        if (frameIndex !== null) { this.lastFrameAt = now; }
        return { frameIndex, progress: Math.min(1, elapsed / this.durationMs), complete: this.frameCount === 3 };
    }
}

class BlinkLivenessChallenge {
    constructor(policy) {
        this.policy = policy;
        this.reset();
    }

    reset() {
        this.samples = [];
        this.baselineLeft = null;
        this.baselineRight = null;
        this.closedAt = null;
        this.reopenedAt = null;
        this.reopenSamples = 0;
        this.lastSeenAt = null;
        this.events = [];
    }

    observe(left, right, now) {
        if (![left, right, now].every(Number.isFinite) || left < 0 || right < 0 || left > 1 || right > 1) {
            this.reset();
            return this.status();
        }
        if (this.lastSeenAt !== null && (now < this.lastSeenAt || now - this.lastSeenAt > 2000)) { this.reset(); }
        this.lastSeenAt = now;
        if (this.events.length >= this.policy.required_count) { return this.status(); }
        if (this.baselineLeft === null) {
            if (left < this.policy.min_open_ear || right < this.policy.min_open_ear) {
                this.samples = [];
                return this.status();
            }
            this.samples.push([left, right]);
            if (this.samples.length === 3) {
                this.baselineLeft = this.samples.reduce((sum, sample) => sum + sample[0], 0) / 3;
                this.baselineRight = this.samples.reduce((sum, sample) => sum + sample[1], 0) / 3;
            }
            return this.status();
        }
        const closed = left <= this.baselineLeft * this.policy.closed_ratio && right <= this.baselineRight * this.policy.closed_ratio;
        const open = left >= this.baselineLeft * this.policy.reopen_ratio && right >= this.baselineRight * this.policy.reopen_ratio;
        if (this.closedAt === null && closed) {
            this.closedAt = now;
            this.closedLeft = left;
            this.closedRight = right;
        }
        if (this.closedAt !== null) {
            if (now - this.closedAt > this.policy.max_closed_ms) {
                this.reset();
                return this.status();
            }
            if (open) {
                if (this.reopenSamples++ === 0) { this.reopenedAt = now; }
                if (this.reopenSamples >= 2) {
                    if (this.reopenedAt - this.closedAt >= this.policy.min_closed_ms) {
                        this.events.push({ closed_at_ms: this.closedAt, opened_at_ms: this.reopenedAt,
                            closed_left_ear: this.closedLeft, closed_right_ear: this.closedRight,
                            reopened_left_ear: left, reopened_right_ear: right });
                    }
                    this.closedAt = null;
                    this.reopenedAt = null;
                    this.reopenSamples = 0;
                }
            } else {
                this.reopenSamples = 0;
                this.reopenedAt = null;
            }
        }
        return this.status();
    }

    status() {
        return { passed: this.events.length === this.policy.required_count, completedCount: this.events.length,
            phase: this.baselineLeft === null ? 'calibrating' : (this.closedAt === null ? 'close' : 'reopen') };
    }

    summary() {
        return { required_count: this.policy.required_count, completed_count: this.events.length,
            passed: this.status().passed, baseline_left_ear: this.baselineLeft, baseline_right_ear: this.baselineRight,
            events: this.events.map(event => ({ ...event })) };
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = PassiveFaceCapture;
    module.exports.BlinkLivenessChallenge = BlinkLivenessChallenge;
}
