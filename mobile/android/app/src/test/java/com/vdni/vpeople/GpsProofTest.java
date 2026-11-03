package com.vdni.vpeople;

import org.junit.Test;
import static org.junit.Assert.assertEquals;

public class GpsProofTest {
    @Test public void onlyFreshUnmockedAccurateSamplesCanBeSigned() {
        assertEquals(GpsProof.Sample.MOCKED, GpsProof.classify(true, 10, 11, 12, 1));
        assertEquals(GpsProof.Sample.MOCKED, GpsProof.classify(true, 10, 0, 12, Float.NaN));
        assertEquals(GpsProof.Sample.WAIT, GpsProof.classify(false, 10, 9, 12, 4));
        assertEquals(GpsProof.Sample.WAIT, GpsProof.classify(false, 10, 13, 12, 4));
        assertEquals(GpsProof.Sample.WAIT, GpsProof.classify(false, 10, 11, 11000000012L, 4));
        assertEquals(GpsProof.Sample.WAIT, GpsProof.classify(false, 10, 11, 12, Float.NaN));
        assertEquals(GpsProof.Sample.WAIT, GpsProof.classify(false, 10, 11, 12, 300));
        assertEquals(GpsProof.Sample.READY, GpsProof.classify(false, 10, 11, 12, 4));
    }
}
