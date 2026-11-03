package com.vdni.vpeople;

import android.content.Context;
import android.location.Location;
import android.location.LocationListener;
import android.location.LocationManager;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.os.SystemClock;
import android.security.keystore.KeyGenParameterSpec;
import android.security.keystore.KeyProperties;
import android.util.Base64;
import org.json.JSONArray;
import org.json.JSONObject;

import java.nio.charset.StandardCharsets;
import java.security.KeyPairGenerator;
import java.security.KeyStore;
import java.security.MessageDigest;
import java.security.PrivateKey;
import java.security.Signature;
import java.security.cert.Certificate;
import java.security.spec.ECGenParameterSpec;
import java.util.concurrent.Executor;

/** One fresh OS fix per server nonce. Coordinates and the mock flag never come from the page. */
final class GpsProof {
    enum Sample { MOCKED, WAIT, READY }

    static Sample classify(boolean mocked, long started, long fixTime, long now, float accuracy) {
        if (mocked) return Sample.MOCKED;
        if (fixTime < started || fixTime > now || now - fixTime > 10000000000L
            || !Float.isFinite(accuracy) || accuracy <= 0 || accuracy > 200) return Sample.WAIT;
        return Sample.READY;
    }

    interface Result { void complete(JSONObject proof, String error); }
    private static final String ALIAS = "vpeople-attendance-gps-v1";
    private final LocationManager locations;
    private final Handler main = new Handler(Looper.getMainLooper());
    private final Executor worker;
    private LocationListener listener;
    private Result pending;
    private final Runnable timeout = () -> finish(null, "Lokasi GPS baru belum tersedia. Aktifkan GPS, cari tempat terbuka, lalu coba lagi.");

    GpsProof(Context context, Executor worker) {
        this.locations = (LocationManager) context.getSystemService(Context.LOCATION_SERVICE);
        this.worker = worker;
    }

    void request(String nonce, String expectedKey, Result result) {
        if (pending != null) { result.complete(null, "Pemeriksaan lokasi masih berjalan. Coba lagi sebentar."); return; }
        pending = result;
        final long started = SystemClock.elapsedRealtimeNanos();
        listener = new LocationListener() {
            @Override public void onLocationChanged(Location fix) {
                if (pending != result || listener != this) return;
                boolean mock = Build.VERSION.SDK_INT >= 31 ? fix.isMock() : fix.isFromMockProvider();
                Sample sample = classify(mock, started, fix.getElapsedRealtimeNanos(), SystemClock.elapsedRealtimeNanos(),
                    fix.hasAccuracy() ? fix.getAccuracy() : Float.NaN);
                if (sample == Sample.MOCKED) {
                    finish(null, "Lokasi simulasi terdeteksi. Matikan Fake GPS dan gunakan lokasi perangkat yang sebenarnya."); return;
                }
                if (sample != Sample.READY) return;
                stopListening();
                worker.execute(() -> {
                    try {
                        JSONObject proof = sign(nonce, expectedKey, fix);
                        main.post(() -> { if (pending == result) finish(proof, null); });
                    } catch (Exception failure) {
                        main.post(() -> { if (pending == result) finish(null, "Kunci keamanan perangkat tidak tersedia. Periksa koneksi dan pembaruan Android atau hubungi HR."); });
                    }
                });
            }
            @Override public void onProviderDisabled(String provider) { if (pending == result) finish(null, "GPS dinonaktifkan. Aktifkan lokasi akurat lalu coba lagi."); }
            @Override public void onProviderEnabled(String provider) {}
            @Override public void onStatusChanged(String provider, int status, Bundle extras) {}
        };
        try {
            if (!locations.isProviderEnabled(LocationManager.GPS_PROVIDER)) {
                finish(null, "Aktifkan GPS perangkat sebelum presensi."); return;
            }
            locations.requestLocationUpdates(LocationManager.GPS_PROVIDER, 0, 0, listener, Looper.getMainLooper());
            main.postDelayed(timeout, 25000);
        } catch (SecurityException | IllegalArgumentException error) {
            finish(null, "Izinkan lokasi akurat untuk V-People, lalu coba lagi.");
        }
    }

    private JSONObject sign(String nonce, String expectedKey, Location fix) throws Exception {
        KeyStore store = KeyStore.getInstance("AndroidKeyStore");
        store.load(null);
        Certificate leaf = store.getCertificate(ALIAS);
        boolean enroll = leaf == null || !id(leaf).equals(expectedKey);
        if (enroll) {
            KeyPairGenerator generator = KeyPairGenerator.getInstance(KeyProperties.KEY_ALGORITHM_EC, "AndroidKeyStore");
            generator.initialize(new KeyGenParameterSpec.Builder(ALIAS, KeyProperties.PURPOSE_SIGN)
                .setAlgorithmParameterSpec(new ECGenParameterSpec("secp256r1"))
                .setDigests(KeyProperties.DIGEST_SHA256).setAttestationChallenge(unhex(nonce)).build());
            generator.generateKeyPair();
            leaf = store.getCertificate(ALIAS);
        }
        String data = "v1|" + nonce + "|" + Math.round(fix.getLatitude() * 1e6) + "|" + Math.round(fix.getLongitude() * 1e6)
            + "|" + Math.round(fix.getAccuracy() * 100) + "|" + (fix.hasSpeed() ? Math.max(0, Math.round(fix.getSpeed() * 100)) : 0);
        Signature signer = Signature.getInstance("SHA256withECDSA");
        signer.initSign((PrivateKey) store.getKey(ALIAS, null));
        signer.update(data.getBytes(StandardCharsets.UTF_8));
        JSONObject proof = new JSONObject().put("key", id(leaf)).put("data", data)
            .put("signature", Base64.encodeToString(signer.sign(), Base64.NO_WRAP));
        if (enroll) {
            JSONArray chain = new JSONArray();
            for (Certificate cert : store.getCertificateChain(ALIAS)) chain.put(Base64.encodeToString(cert.getEncoded(), Base64.NO_WRAP));
            proof.put("chain", chain);
        }
        return proof;
    }

    private String id(Certificate cert) throws Exception {
        StringBuilder hex = new StringBuilder();
        for (byte b : MessageDigest.getInstance("SHA-256").digest(cert.getEncoded())) hex.append(String.format(java.util.Locale.ROOT, "%02x", b));
        return hex.toString();
    }

    private byte[] unhex(String input) {
        byte[] bytes = new byte[input.length() / 2];
        for (int i = 0; i < bytes.length; i++) bytes[i] = (byte) Integer.parseInt(input.substring(i * 2, i * 2 + 2), 16);
        return bytes;
    }

    private void stopListening() {
        if (listener != null) {
            try { locations.removeUpdates(listener); } catch (SecurityException revoked) { /* Permission was withdrawn. */ }
            listener = null;
        }
    }

    private void finish(JSONObject proof, String error) {
        Result result = pending;
        pending = null;
        main.removeCallbacks(timeout);
        stopListening();
        if (result != null) result.complete(proof, error);
    }

    void cancel() { finish(null, "Pemeriksaan lokasi terhenti. Buka halaman presensi dan coba lagi."); }
}
