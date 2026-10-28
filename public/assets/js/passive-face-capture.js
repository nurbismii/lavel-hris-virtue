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

if (typeof module !== 'undefined' && module.exports) { module.exports = PassiveFaceCapture; }
