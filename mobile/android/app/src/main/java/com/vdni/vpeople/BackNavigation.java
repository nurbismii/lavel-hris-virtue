package com.vdni.vpeople;

import java.net.URI;

final class BackNavigation {
    enum Action { RETURN_HOME, HINT_EXIT, EXIT }
    private final OriginPolicy origin;
    private final String loginUrl;
    private String homeUrl;
    private long lastBackAt = -1;

    BackNavigation(String baseUrl) {
        origin = new OriginPolicy(baseUrl);
        loginUrl = URI.create(baseUrl).resolve("/login").toString();
        homeUrl = loginUrl;
    }

    String homeUrl() { return homeUrl; }
    void reset() { lastBackAt = -1; }

    void updateHome(String pageUrl, String advertisedHome) {
        if (!origin.allows(pageUrl)) return;
        if (isEntry(path(pageUrl))) homeUrl = loginUrl;
        else if (origin.allows(advertisedHome)) homeUrl = withoutQuery(advertisedHome);
        // Older web deployments have no home metadata; retain their observed dashboard.
        else if (homeUrl.equals(loginUrl) && isDashboard(path(pageUrl))) homeUrl = withoutQuery(pageUrl);
    }

    Action press(String pageUrl, long elapsedMillis) {
        if (!isRoot(pageUrl)) {
            reset();
            return Action.RETURN_HOME;
        }
        boolean repeated = lastBackAt >= 0 && elapsedMillis >= lastBackAt && elapsedMillis - lastBackAt <= 2000;
        lastBackAt = elapsedMillis;
        return repeated ? Action.EXIT : Action.HINT_EXIT;
    }

    private boolean isRoot(String url) {
        if (!origin.allows(url)) return true;
        String currentPath = path(url);
        return isEntry(currentPath) || currentPath.equals(path(homeUrl))
            || (homeUrl.equals(loginUrl) && isDashboard(currentPath));
    }

    private boolean isEntry(String path) { return path.equals("/") || path.equals("/login") || path.equals("/email/verify"); }
    private boolean isDashboard(String path) { return path.equals("/dashboard") || path.equals("/home") || path.equals("/admin/home"); }
    private String withoutQuery(String url) { return url.split("[?#]", 2)[0]; }
    private String path(String url) {
        String path = URI.create(url).getPath();
        if (path == null || path.isEmpty()) return "/";
        return path.length() > 1 && path.endsWith("/") ? path.substring(0, path.length() - 1) : path;
    }
}
