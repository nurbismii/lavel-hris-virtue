package com.vdni.vpeople;

import java.net.URI;

final class OriginPolicy {
    private final URI origin;
    OriginPolicy(String base) { origin = URI.create(base); }
    boolean allows(String value) {
        try {
            URI candidate = URI.create(value);
            return "https".equalsIgnoreCase(candidate.getScheme()) && candidate.getRawUserInfo() == null
                && origin.getHost().equalsIgnoreCase(candidate.getHost())
                && port(origin) == port(candidate);
        } catch (IllegalArgumentException | NullPointerException exception) { return false; }
    }
    private int port(URI uri) { return uri.getPort() == -1 ? 443 : uri.getPort(); }
}
