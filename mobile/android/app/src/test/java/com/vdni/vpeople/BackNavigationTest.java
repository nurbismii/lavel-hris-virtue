package com.vdni.vpeople;

import org.junit.Test;
import static org.junit.Assert.*;
import static com.vdni.vpeople.BackNavigation.Action.*;

public class BackNavigationTest {
    private static final String BASE = "https://hris.example.test";

    @Test public void menuBackReturnsHomeThenRequiresTwoFreshPressesToExit() {
        BackNavigation back = new BackNavigation(BASE);
        back.updateHome(BASE + "/slipgaji", BASE + "/dashboard");
        for (String menu : new String[]{"/presensi", "/mobile/layanan", "/slipgaji/2026-11"}) {
            assertEquals(RETURN_HOME, back.press(BASE + menu, 1000));
            assertEquals(BASE + "/dashboard", back.homeUrl());
        }
        assertEquals(HINT_EXIT, back.press(BASE + "/dashboard", 1100));
        assertEquals(EXIT, back.press(BASE + "/dashboard", 2000));
    }

    @Test public void advertisedHomeFollowsEachRoleAndIgnoresQueryAndFragment() {
        for (String home : new String[]{"/dashboard", "/home", "/pengaturan-akun/update"}) {
            BackNavigation back = new BackNavigation(BASE);
            back.updateHome(BASE + "/mobile/layanan", BASE + home + "?filter=1#card");
            assertEquals(BASE + home, back.homeUrl());
            assertEquals(RETURN_HOME, back.press(BASE + "/slipgaji", 0));
            assertEquals(HINT_EXIT, back.press(BASE + home + "/?tab=1#summary", 10));
            assertEquals(EXIT, back.press(BASE + home, 2010));
        }
    }

    @Test public void exitArmingExpiresAndResetsWhenNavigatingOrLeavingTheApp() {
        BackNavigation back = new BackNavigation(BASE);
        back.updateHome(BASE + "/dashboard", BASE + "/dashboard");
        assertEquals(HINT_EXIT, back.press(BASE + "/dashboard", 1000));
        assertEquals(HINT_EXIT, back.press(BASE + "/dashboard", 3001));
        assertEquals(RETURN_HOME, back.press(BASE + "/slipgaji", 3100));
        assertEquals(HINT_EXIT, back.press(BASE + "/dashboard", 3200));
        back.reset();
        assertEquals(HINT_EXIT, back.press(BASE + "/dashboard", 3300));
        assertEquals(EXIT, back.press(BASE + "/dashboard", 3400));
    }

    @Test public void oldWebDeploymentsUseTheirObservedDashboard() {
        BackNavigation back = new BackNavigation(BASE);
        assertEquals(RETURN_HOME, back.press(BASE + "/slipgaji", 0));
        assertEquals(BASE + "/login", back.homeUrl());
        back.updateHome(BASE + "/home?filter=1", null);
        assertEquals(BASE + "/home", back.homeUrl());
        back.updateHome(BASE + "/dashboard", null);
        assertEquals(BASE + "/home", back.homeUrl());
        assertEquals(RETURN_HOME, back.press(BASE + "/slipgaji", 10));
        assertEquals(HINT_EXIT, back.press(BASE + "/home", 20));
    }

    @Test public void homeMetadataCannotRedirectToAnotherOriginOrComeFromAnUntrustedPage() {
        BackNavigation back = new BackNavigation(BASE);
        back.updateHome(BASE + "/dashboard", BASE + "/dashboard");
        for (String url : new String[]{"https://evil.test/home", "http://hris.example.test/home",
            "https://hris.example.test:8443/home", "https://user@hris.example.test/home", "javascript:alert(1)", null}) {
            back.updateHome(BASE + "/slipgaji", url);
            assertEquals(BASE + "/dashboard", back.homeUrl());
        }
        back.updateHome("https://evil.test/slipgaji", BASE + "/home");
        assertEquals(BASE + "/dashboard", back.homeUrl());
    }

    @Test public void loggedOutUsersReturnToLoginAndCannotReopenTheOldAccountViaBack() {
        BackNavigation back = new BackNavigation(BASE);
        back.updateHome(BASE + "/dashboard", BASE + "/dashboard");
        back.updateHome(BASE + "/login?expired=1", null);
        assertEquals(BASE + "/login", back.homeUrl());
        assertEquals(RETURN_HOME, back.press(BASE + "/forgot-password", 0));
        assertEquals(HINT_EXIT, back.press(BASE + "/login", 100));
        assertEquals(EXIT, back.press(BASE + "/login", 200));
    }

    @Test public void startupAndVersionCheckScreensCanCloseWithoutOpeningTheWebsite() {
        BackNavigation back = new BackNavigation(BASE);
        assertEquals(HINT_EXIT, back.press(null, 0));
        assertEquals(EXIT, back.press(null, 100));
        back.reset();
        assertEquals(HINT_EXIT, back.press("about:blank", 200));
    }
}
