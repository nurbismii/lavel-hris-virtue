package com.vdni.vpeople;

import android.app.Activity;
import android.app.Instrumentation;
import android.content.Intent;
import android.content.res.TypedArray;
import android.graphics.Insets;
import android.os.Build;
import android.os.Bundle;
import android.view.View;
import android.view.ViewGroup;
import android.view.WindowInsets;
import android.view.WindowInsetsController;
import android.view.WindowManager;
import java.util.ArrayList;
import java.util.List;

public class WindowInsetsCheck extends Instrumentation {
    private final List<String> failures = new ArrayList<>();

    @Override public void onCreate(Bundle arguments) { super.onCreate(arguments); start(); }

    @Override public void onStart() {
        Bundle test = new Bundle();
        test.putString("class", getClass().getName()); test.putString("test", "systemBarsAndInsets");
        test.putInt("numtests", 1); test.putInt("current", 1); sendStatus(1, test);
        Activity activity = null;
        try {
            activity = startActivitySync(new Intent(getTargetContext(), MainActivity.class).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK));
            waitForIdleSync();
            final Activity target = activity;
            runOnMainSync(() -> {
                try { checkWindow(target); }
                catch (Throwable error) { failures.add(error.toString()); }
            });
        } catch (Throwable error) { failures.add(error.toString()); }
        finally { if (activity != null) { final Activity target = activity; runOnMainSync(target::finish); } }
        boolean passed = failures.isEmpty();
        test.putString("stack", String.join("\n", failures)); sendStatus(passed ? 0 : -2, test);
        Bundle result = new Bundle();
        result.putString("stream", passed ? "\nOK (1 test)\n" : "\nFAILURES: " + String.join("; ", failures) + "\n");
        finish(Activity.RESULT_OK, result);
    }

    private void check(boolean condition, String message) { if (!condition) failures.add(message); }

    private void checkWindow(Activity activity) {
        TypedArray theme = activity.obtainStyledAttributes(new int[]{android.R.attr.windowLightStatusBar, android.R.attr.windowLightNavigationBar});
        check(theme.getBoolean(0, false), "Status bar must have dark icons on white");
        check(theme.getBoolean(1, false), "Navigation bar must have dark icons on white");
        theme.recycle();
        check((activity.getWindow().getAttributes().flags & WindowManager.LayoutParams.FLAG_FULLSCREEN) == 0, "Status bar must remain visible");
        if (Build.VERSION.SDK_INT >= 30) {
            int appearance = activity.getWindow().getInsetsController().getSystemBarsAppearance();
            check((appearance & WindowInsetsController.APPEARANCE_LIGHT_STATUS_BARS) != 0, "Status bar appearance must use dark icons");
            check((appearance & WindowInsetsController.APPEARANCE_LIGHT_NAVIGATION_BARS) != 0, "Navigation appearance must use dark icons");
        } else {
            int visibility = activity.getWindow().getDecorView().getSystemUiVisibility();
            check((visibility & View.SYSTEM_UI_FLAG_LIGHT_STATUS_BAR) != 0, "Legacy status icons must be dark");
            check((visibility & View.SYSTEM_UI_FLAG_LIGHT_NAVIGATION_BAR) != 0, "Legacy navigation icons must be dark");
        }
        ViewGroup root = (ViewGroup) ((ViewGroup) activity.findViewById(android.R.id.content)).getChildAt(0);
        int[] location = new int[2]; root.getLocationInWindow(location);
        check(root.getHeight() > 0, "Root must be laid out before checking window bounds");
        check(location[1] == 0, "Window must not add a second top inset");
        check(root.getHeight() == activity.getWindow().getDecorView().getHeight(), "Window must not add a second bottom inset");
        View probe = new View(activity);
        final WindowInsets[] received = new WindowInsets[1];
        probe.setOnApplyWindowInsetsListener((view, insets) -> { received[0] = insets; return insets; });
        root.addView(probe, new ViewGroup.LayoutParams(0, 0));
        for (int bottom : new int[]{48, 24, 0, 320, 48}) {
            WindowInsets input;
            if (Build.VERSION.SDK_INT >= 30) {
                int bars = WindowInsets.Type.systemBars() | WindowInsets.Type.displayCutout();
                input = new WindowInsets.Builder()
                    .setInsets(bars, Insets.of(5, 24, 7, bottom == 320 ? 48 : bottom))
                    .setInsetsIgnoringVisibility(bars, Insets.of(5, 24, 7, 48))
                    .setInsets(WindowInsets.Type.ime(), Insets.of(0, 0, 0, bottom == 320 ? 320 : 0)).build();
            } else {
                input = new WindowInsets(root.getRootWindowInsets()).replaceSystemWindowInsets(5, 24, 7, bottom);
            }
            root.dispatchApplyWindowInsets(input);
            root.dispatchApplyWindowInsets(input);
            check(root.getPaddingTop() == 24 && root.getPaddingBottom() == bottom, "Insets must apply once: " + bottom);
            check(root.getPaddingLeft() == 5 && root.getPaddingRight() == 7, "Landscape side insets must be preserved");
            check(received[0] != null, "WebView children must receive inset updates");
            if (received[0] != null) {
                check(received[0].getSystemWindowInsetTop() == 0 && received[0].getSystemWindowInsetBottom() == 0,
                    "Handled insets must be zero in descendants");
                if (Build.VERSION.SDK_INT >= 30) {
                    check(received[0].getInsets(WindowInsets.Type.systemBars() | WindowInsets.Type.displayCutout() | WindowInsets.Type.ime()).equals(Insets.NONE), "WebView must not apply safe area or IME twice");
                }
            }
        }
        if (Build.VERSION.SDK_INT >= 30) {
            root.dispatchApplyWindowInsets(new WindowInsets.Builder()
                .setInsets(WindowInsets.Type.systemBars(), Insets.of(0, 24, 0, 48))
                .setInsets(WindowInsets.Type.displayCutout(), Insets.of(34, 80, 0, 0)).build());
            check(root.getPaddingTop() == 80 && root.getPaddingLeft() == 34 && root.getPaddingBottom() == 48,
                "Cutout must use the maximum safe area, not sum it with system bars");
        }
        root.removeView(probe);
        android.webkit.WebView web = (android.webkit.WebView) root.getChildAt(3);
        web.setVisibility(View.INVISIBLE);
        activity.onBackPressed();
        check(!activity.isFinishing(), "First Back must show the exit hint");
        activity.onBackPressed();
        check(activity.isFinishing(), "Second Back must close the task");
    }
}
