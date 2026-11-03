package com.vdni.vpeople;

import org.junit.Test;
import static org.junit.Assert.*;

public class OriginPolicyTest {
    @Test public void keepsCredentialsOnExactHttpsOrigin() {
        OriginPolicy policy = new OriginPolicy("https://hris.example.test");
        assertTrue(policy.allows("https://hris.example.test/login?redirect=/dashboard"));
        assertTrue(policy.allows("https://hris.example.test:443/slipgaji"));
        for (String url : new String[] {"http://hris.example.test", "https://hris.example.test.evil.test",
            "https://hris.example.test@evil.test", "https://evil@hris.example.test", "file:///private",
            "javascript:alert(1)", "https://hris.example.test:8443", "intent://app", null}) {
            assertFalse(String.valueOf(url), policy.allows(url));
        }
    }
}
