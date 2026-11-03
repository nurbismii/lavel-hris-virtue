package com.vdni.vpeople;

import android.Manifest;
import android.app.Activity;
import android.app.AlertDialog;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.view.View;
import android.webkit.*;
import android.widget.*;
import org.json.JSONObject;
import java.io.*;
import java.net.URL;
import java.util.Arrays;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import javax.net.ssl.HttpsURLConnection;

public class MainActivity extends Activity {
    private static final int CAMERA = 10, LOCATION = 11, PICK_FILE = 12, SAVE_FILE = 13;
    private final OriginPolicy policy = new OriginPolicy(BuildConfig.HRIS_URL);
    private final ExecutorService worker = Executors.newSingleThreadExecutor();
    private WebView web;
    private GpsProof gpsProof;
    private ProgressBar progress;
    private TextView message;
    private Button retry;
    private ValueCallback<Uri[]> fileCallback;
    private PermissionRequest cameraRequest;
    private GeolocationPermissions.Callback locationCallback;
    private String locationOrigin;
    private File pendingDownload;
    private boolean busyDownload;

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setBackgroundColor(Color.WHITE);
        root.setOnApplyWindowInsetsListener((view, insets) -> {
            view.setPadding(insets.getSystemWindowInsetLeft(), insets.getSystemWindowInsetTop(),
                insets.getSystemWindowInsetRight(), insets.getSystemWindowInsetBottom());
            return insets;
        });
        progress = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        progress.setVisibility(View.GONE);
        message = new TextView(this); message.setPadding(24, 12, 24, 12); message.setAccessibilityLiveRegion(View.ACCESSIBILITY_LIVE_REGION_POLITE);
        retry = new Button(this); retry.setText(R.string.retry); retry.setVisibility(View.GONE);
        retry.setOnClickListener(v -> checkVersion());
        web = new WebView(this);
        gpsProof = new GpsProof(this, worker);
        root.addView(progress); root.addView(message); root.addView(retry);
        root.addView(web, new LinearLayout.LayoutParams(-1, 0, 1));
        setContentView(root);
        WebSettings settings = web.getSettings();
        // HRIS camera and forms require JavaScript; native permissions and navigation remain origin-restricted.
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setAllowFileAccess(false);
        settings.setAllowContentAccess(false);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        settings.setGeolocationEnabled(true);
        settings.setUserAgentString(settings.getUserAgentString() + " VPEOPLE_APP/" + BuildConfig.VERSION_NAME);
        CookieManager.getInstance().setAcceptThirdPartyCookies(web, false);
        WebView.setWebContentsDebuggingEnabled(BuildConfig.DEBUG);
        web.setWebViewClient(new Browser());
        web.setWebChromeClient(new Chrome());
        web.setDownloadListener((url, agent, disposition, mime, length) -> download(url, disposition, mime));
        File[] stale = getCacheDir().listFiles((dir, name) -> name.startsWith("vpeople-download-"));
        if (stale != null) for (File file : stale) file.delete();
        checkVersion();
    }

    private void status(String text, boolean loading) {
        message.setText(text);
        message.setVisibility(text.isEmpty() ? View.GONE : View.VISIBLE);
        progress.setVisibility(loading ? View.VISIBLE : View.GONE);
    }

    private void failure(String text) {
        status(text, false);
        retry.setVisibility(View.VISIBLE);
    }

    private HttpsURLConnection connect(String url) throws IOException {
        if (!policy.allows(url)) throw new IOException("Untrusted origin");
        HttpsURLConnection connection = (HttpsURLConnection) new URL(url).openConnection();
        connection.setInstanceFollowRedirects(false);
        connection.setConnectTimeout(15000); connection.setReadTimeout(20000);
        connection.setRequestProperty("User-Agent", webAgent());
        return connection;
    }

    private String webAgent() { return "Android VPEOPLE_APP/" + BuildConfig.VERSION_NAME; }

    private void checkVersion() {
        if (progress.getVisibility() == View.VISIBLE) return;
        retry.setVisibility(View.GONE);
        web.setVisibility(View.INVISIBLE);
        status("Memeriksa versi aplikasi…", true);
        worker.execute(() -> {
            HttpsURLConnection connection = null;
            try {
                connection = connect(BuildConfig.HRIS_URL + "/api/mobile/app-version?platform=android&version_code=" + BuildConfig.VERSION_CODE);
                connection.setRequestProperty("Accept", "application/json");
                if (connection.getResponseCode() != 200) throw new IOException("Version check failed");
                StringBuilder body = new StringBuilder();
                try (BufferedReader reader = new BufferedReader(new InputStreamReader(connection.getInputStream(), "UTF-8"))) {
                    String line;
                    while ((line = reader.readLine()) != null) {
                        body.append(line);
                        if (body.length() > 65536) throw new IOException("Response too large");
                    }
                }
                JSONObject result = new JSONObject(body.toString());
                if (!result.getBoolean("success")) throw new IOException("Invalid response");
                boolean required = result.getBoolean("force_update");
                runOnUiThread(() -> {
                    if (isDestroyed()) return;
                    if (required) {
                        failure("Versi aplikasi ini perlu diperbarui sebelum masuk.");
                        new AlertDialog.Builder(this).setTitle("Perbarui V-People")
                            .setMessage("Unduh versi terbaru melalui halaman resmi V-People.")
                            .setPositiveButton("Unduh", (dialog, which) -> openExternal(Uri.parse(BuildConfig.HRIS_URL + "/download-app")))
                            .setNegativeButton("Tutup", null).show();
                    } else {
                        web.setVisibility(View.VISIBLE);
                        web.loadUrl(BuildConfig.HRIS_URL + "/login");
                    }
                });
            } catch (Exception exception) {
                runOnUiThread(() -> { if (!isDestroyed()) failure("Tidak dapat terhubung. Periksa jaringan, lalu coba lagi."); });
            } finally { if (connection != null) connection.disconnect(); }
        });
    }

    private void openExternal(Uri uri) {
        String scheme = uri.getScheme();
        if (!Arrays.asList("https", "mailto", "tel").contains(scheme)) {
            failure("Tautan ini tidak didukung aplikasi."); return;
        }
        try { startActivity(new Intent(Intent.ACTION_VIEW, uri)); }
        catch (ActivityNotFoundException exception) { failure("Tidak ada aplikasi untuk membuka tautan ini."); }
    }

    private class Browser extends WebViewClient {
        private android.window.OnBackInvokedCallback backCallback;

        @Override public void doUpdateVisitedHistory(WebView view, String url, boolean reload) {
            if (android.os.Build.VERSION.SDK_INT < 33) return;
            if (view.canGoBack() && backCallback == null) {
                backCallback = view::goBack;
                getOnBackInvokedDispatcher().registerOnBackInvokedCallback(
                    android.window.OnBackInvokedDispatcher.PRIORITY_DEFAULT, backCallback);
            } else if (!view.canGoBack() && backCallback != null) {
                getOnBackInvokedDispatcher().unregisterOnBackInvokedCallback(backCallback);
                backCallback = null;
            }
        }
        @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
            Uri target = request.getUrl();
            if ("vpeople".equals(target.getScheme()) && "gps-proof".equals(target.getHost())) {
                if (request.isForMainFrame() && isAttendancePage()) requestGpsProof(target);
                return true;
            }
            if (!request.isForMainFrame()) return !policy.allows(request.getUrl().toString());
            if (policy.allows(request.getUrl().toString())) return false;
            openExternal(request.getUrl()); return true;
        }
        @Override public void onPageStarted(WebView view, String url, android.graphics.Bitmap icon) {
            gpsProof.cancel();
            status("Memuat halaman…", true);
        }
        @Override public void onPageFinished(WebView view, String url) {
            if (retry.getVisibility() != View.VISIBLE && !busyDownload) status("", false);
            CookieManager.getInstance().flush();
        }
        @Override public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
            if (request.isForMainFrame()) failure("Halaman tidak dapat dimuat. Periksa koneksi dan coba lagi.");
        }
        @Override public void onReceivedHttpError(WebView view, WebResourceRequest request, WebResourceResponse response) {
            if (request.isForMainFrame() && response.getStatusCode() >= 500) failure("Layanan sedang bermasalah. Silakan coba lagi.");
        }
        @Override public void onReceivedSslError(WebView view, SslErrorHandler handler, android.net.http.SslError error) {
            handler.cancel(); failure("Koneksi aman tidak dapat diverifikasi. Hubungi admin.");
        }
    }

    private boolean isAttendancePage() {
        String url = web.getUrl();
        return policy.allows(url) && "/presensi".equals(Uri.parse(url).getPath());
    }

    private void requestGpsProof(Uri target) {
        String nonce = target.getQueryParameter("nonce");
        String key = target.getQueryParameter("key");
        if (nonce == null || !nonce.matches("[a-f0-9]{64}") || key == null || !key.matches("new|[a-f0-9]{64}")) return;
        gpsProof.request(nonce, key, (proof, error) -> {
            if (isDestroyed() || !isAttendancePage()) return;
            JSONObject detail = new JSONObject();
            try { detail.put("nonce", nonce).put("proof", proof).put("error", error); }
            catch (org.json.JSONException invalid) { return; }
            web.evaluateJavascript("window.dispatchEvent(new CustomEvent('vpeople:gps',{detail:" + detail + "}))", null);
        });
    }

    private class Chrome extends WebChromeClient {
        @Override public void onProgressChanged(WebView view, int value) { progress.setProgress(value); }
        @Override public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
            if (!policy.allows(web.getUrl())) { callback.onReceiveValue(null); return true; }
            if (fileCallback != null) fileCallback.onReceiveValue(null);
            fileCallback = callback;
            try { startActivityForResult(params.createIntent(), PICK_FILE); }
            catch (ActivityNotFoundException exception) { fileCallback.onReceiveValue(null); fileCallback = null; failure("Pemilih berkas tidak tersedia."); }
            return true;
        }
        @Override public void onPermissionRequest(PermissionRequest request) {
            if (!policy.allows(request.getOrigin().toString()) || !policy.allows(web.getUrl())
                || cameraRequest != null || request.getResources().length != 1
                || !PermissionRequest.RESOURCE_VIDEO_CAPTURE.equals(request.getResources()[0])) { request.deny(); return; }
            if (checkSelfPermission(Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
                request.grant(new String[]{PermissionRequest.RESOURCE_VIDEO_CAPTURE});
            } else { cameraRequest = request; requestPermissions(new String[]{Manifest.permission.CAMERA}, CAMERA); }
        }
        @Override public void onPermissionRequestCanceled(PermissionRequest request) {
            if (cameraRequest == request) cameraRequest = null;
        }
        @Override public void onGeolocationPermissionsShowPrompt(String origin, GeolocationPermissions.Callback callback) {
            if (!policy.allows(origin) || !policy.allows(web.getUrl()) || locationCallback != null) { callback.invoke(origin, false, false); return; }
            if (checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED) {
                callback.invoke(origin, true, false);
            } else {
                locationOrigin = origin; locationCallback = callback;
                requestPermissions(new String[]{Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION}, LOCATION);
            }
        }
        @Override public void onGeolocationPermissionsHidePrompt() { locationCallback = null; }
    }

    @Override public void onRequestPermissionsResult(int code, String[] permissions, int[] grants) {
        super.onRequestPermissionsResult(code, permissions, grants);
        boolean granted = grants.length > 0 && grants[0] == PackageManager.PERMISSION_GRANTED && policy.allows(web.getUrl());
        if (code == CAMERA && cameraRequest != null) {
            if (granted) cameraRequest.grant(new String[]{PermissionRequest.RESOURCE_VIDEO_CAPTURE}); else cameraRequest.deny();
            cameraRequest = null;
        }
        if (code == LOCATION && locationCallback != null) {
            locationCallback.invoke(locationOrigin, granted, false); locationCallback = null;
        }
        if (!granted) failure("Izin ditolak. Aktifkan izin kamera atau lokasi akurat di Pengaturan aplikasi, lalu coba lagi.");
    }

    private void download(String url, String disposition, String mime) {
        if (busyDownload) return;
        if (!policy.allows(url)) { failure("Unduhan hanya diizinkan dari layanan HRIS."); return; }
        busyDownload = true;
        status("Mengunduh dokumen…", true);
        String cookies = CookieManager.getInstance().getCookie(url);
        String filename = new File(URLUtil.guessFileName(url, disposition, mime)).getName();
        worker.execute(() -> {
            File file = null;
            HttpsURLConnection connection = null;
            try {
                String next = url;
                for (int redirects = 0; redirects < 6; redirects++) {
                    connection = connect(next);
                    if (cookies != null) connection.setRequestProperty("Cookie", cookies);
                    int response = connection.getResponseCode();
                    if (response >= 300 && response < 400) {
                        String location = connection.getHeaderField("Location");
                        if (location == null) throw new IOException("Missing redirect");
                        next = new URL(new URL(next), location).toString();
                        connection.disconnect(); continue;
                    }
                    if (response != 200 || String.valueOf(connection.getContentType()).startsWith("text/html")) throw new IOException("Download failed");
                    file = File.createTempFile("vpeople-download-", ".tmp", getCacheDir());
                    try (InputStream input = connection.getInputStream(); OutputStream output = new FileOutputStream(file)) {
                        byte[] buffer = new byte[32768]; int count; long total = 0;
                        while ((count = input.read(buffer)) != -1) {
                            total += count;
                            if (total > 100L * 1024 * 1024) throw new IOException("File too large");
                            output.write(buffer, 0, count);
                        }
                    }
                    File complete = file;
                    runOnUiThread(() -> {
                        if (isDestroyed()) { complete.delete(); return; }
                        pendingDownload = complete;
                        status("Pilih lokasi penyimpanan dokumen.", false);
                        Intent save = new Intent(Intent.ACTION_CREATE_DOCUMENT).addCategory(Intent.CATEGORY_OPENABLE)
                            .setType(mime == null ? "application/octet-stream" : mime).putExtra(Intent.EXTRA_TITLE, filename);
                        try { startActivityForResult(save, SAVE_FILE); }
                        catch (ActivityNotFoundException exception) {
                            complete.delete(); pendingDownload = null; busyDownload = false; failure("Penyimpanan dokumen tidak tersedia.");
                        }
                    });
                    return;
                }
                throw new IOException("Too many redirects");
            } catch (Exception exception) {
                if (file != null) file.delete();
                runOnUiThread(() -> { busyDownload = false; if (!isDestroyed()) failure("Dokumen gagal diunduh. Periksa koneksi atau login kembali."); });
            } finally { if (connection != null) connection.disconnect(); }
        });
    }

    @Override protected void onActivityResult(int request, int result, Intent data) {
        super.onActivityResult(request, result, data);
        if (request == PICK_FILE && fileCallback != null) {
            Uri[] selected = WebChromeClient.FileChooserParams.parseResult(result, data);
            if (selected != null) for (Uri uri : selected) {
                if (!"content".equals(uri.getScheme())) { selected = null; break; }
            }
            fileCallback.onReceiveValue(selected); fileCallback = null;
        }
        if (request == SAVE_FILE && pendingDownload != null) {
            File file = pendingDownload; pendingDownload = null;
            if (result != RESULT_OK || data == null || data.getData() == null) {
                file.delete(); busyDownload = false; status("Penyimpanan dibatalkan.", false); return;
            }
            Uri destination = data.getData();
            status("Menyimpan dokumen…", true);
            worker.execute(() -> {
                try (InputStream input = new FileInputStream(file); OutputStream output = getContentResolver().openOutputStream(destination)) {
                    if (output == null) throw new IOException("Destination unavailable");
                    byte[] buffer = new byte[32768]; int count;
                    while ((count = input.read(buffer)) != -1) output.write(buffer, 0, count);
                    runOnUiThread(() -> { if (!isDestroyed()) status("Dokumen berhasil disimpan.", false); });
                } catch (Exception exception) {
                    runOnUiThread(() -> { if (!isDestroyed()) failure("Dokumen gagal disimpan. Pilih lokasi lain dan unduh kembali."); });
                } finally { file.delete(); busyDownload = false; }
            });
        }
    }

    @Override public void onBackPressed() { if (web.canGoBack()) web.goBack(); else super.onBackPressed(); }
    @Override protected void onPause() { super.onPause(); gpsProof.cancel(); web.onPause(); CookieManager.getInstance().flush(); }
    @Override protected void onResume() { super.onResume(); if (web != null) web.onResume(); }
    @Override protected void onDestroy() {
        if (fileCallback != null) fileCallback.onReceiveValue(null);
        if (cameraRequest != null) cameraRequest.deny();
        if (locationCallback != null) locationCallback.invoke(locationOrigin, false, false);
        if (pendingDownload != null) pendingDownload.delete();
        web.destroy(); worker.shutdownNow(); super.onDestroy();
    }
}
