import UIKit
import WebKit

final class HRISViewController: UIViewController, WKNavigationDelegate, WKUIDelegate, WKDownloadDelegate, URLSessionTaskDelegate {
    private let base = URL(string: Bundle.main.object(forInfoDictionaryKey: "HRISBaseURL") as? String ?? "") ?? URL(string: "about:blank")!
    private lazy var policy = OriginPolicy(base: base)
    private var web: WKWebView!
    private let message = UILabel()
    private let spinner = UIActivityIndicatorView(style: .medium)
    private let retry = UIButton(type: .system)
    private var checkingVersion = false
    private var downloads: [ObjectIdentifier: URL] = [:]
    private lazy var versionSession = URLSession(configuration: .ephemeral, delegate: self, delegateQueue: nil)

    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .systemBackground
        retry.setTitle("Coba lagi", for: .normal)
        retry.isHidden = true
        retry.addTarget(self, action: #selector(checkVersion), for: .touchUpInside)
        let configuration = WKWebViewConfiguration()
        configuration.applicationNameForUserAgent = "VPEOPLE_APP/1.0.0"
        configuration.allowsInlineMediaPlayback = true
        configuration.websiteDataStore = .default()
        configuration.defaultWebpagePreferences.preferredContentMode = .mobile
        web = WKWebView(frame: .zero, configuration: configuration)
        web.navigationDelegate = self
        web.uiDelegate = self
        web.allowsBackForwardNavigationGestures = true
        message.numberOfLines = 0
        message.font = .preferredFont(forTextStyle: .footnote)
        message.adjustsFontForContentSizeCategory = true
        message.textAlignment = .center
        message.textColor = .secondaryLabel
        let stack = UIStackView(arrangedSubviews: [spinner, message, retry, web])
        stack.axis = .vertical
        stack.spacing = 8
        stack.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(stack)
        NSLayoutConstraint.activate([
            stack.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor),
            stack.bottomAnchor.constraint(equalTo: view.safeAreaLayoutGuide.bottomAnchor),
            stack.leadingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.leadingAnchor),
            stack.trailingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.trailingAnchor)
        ])
        // Interrupted downloads are private temporary files, never a permanent employee-document cache.
        let temporary = FileManager.default.temporaryDirectory
        for folder in (try? FileManager.default.contentsOfDirectory(at: temporary, includingPropertiesForKeys: nil)) ?? [] {
            if folder.lastPathComponent.hasPrefix("vpeople-"), UUID(uuidString: String(folder.lastPathComponent.dropFirst(8))) != nil {
                try? FileManager.default.removeItem(at: folder)
            }
        }
        checkVersion()
    }

    private func status(_ text: String, loading: Bool = false, canRetry: Bool = false) {
        retry.isHidden = !canRetry
        message.text = text
        message.isHidden = text.isEmpty
        if loading { spinner.startAnimating() } else { spinner.stopAnimating() }
    }

    @objc private func checkVersion() {
        guard !checkingVersion else { return }
        guard policy.allows(base), base.path.isEmpty || base.path == "/", base.query == nil, base.fragment == nil else {
            status("Alamat layanan tidak valid. Hubungi admin."); return
        }
        checkingVersion = true
        web.isHidden = true
        status("Memeriksa versi aplikasi…", loading: true)
        var request = URLRequest(url: base.appendingPathComponent("api/mobile/app-version"))
        var components = URLComponents(url: request.url!, resolvingAgainstBaseURL: false)!
        components.queryItems = [URLQueryItem(name: "platform", value: "ios"), URLQueryItem(name: "version_code", value: Bundle.main.object(forInfoDictionaryKey: "CFBundleVersion") as? String ?? "1")]
        request.url = components.url
        request.timeoutInterval = 20
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        versionSession.dataTask(with: request) { [weak self] data, response, error in
            let object = data.flatMap { try? JSONSerialization.jsonObject(with: $0) } as? [String: Any]
            DispatchQueue.main.async {
                guard let self = self else { return }
                self.checkingVersion = false
                guard error == nil, (response as? HTTPURLResponse)?.statusCode == 200,
                      object?["success"] as? Bool == true, let required = object?["force_update"] as? Bool else {
                    self.status("Tidak dapat terhubung. Periksa jaringan, lalu coba lagi.", canRetry: true); return
                }
                if required {
                    self.status("Perbarui aplikasi sebelum masuk.", canRetry: true)
                    let alert = UIAlertController(title: "Perbarui V-People", message: "Unduh versi terbaru melalui halaman resmi V-People.", preferredStyle: .alert)
                    alert.addAction(UIAlertAction(title: "Unduh", style: .default) { _ in
                        UIApplication.shared.open(self.base.appendingPathComponent("download-app"))
                    })
                    alert.addAction(UIAlertAction(title: "Tutup", style: .cancel))
                    self.present(alert, animated: true)
                } else {
                    self.web.isHidden = false
                    self.web.load(URLRequest(url: self.base.appendingPathComponent("login")))
                }
            }
        }.resume()
    }

    func urlSession(_ session: URLSession, task: URLSessionTask, willPerformHTTPRedirection response: HTTPURLResponse, newRequest request: URLRequest, completionHandler: @escaping (URLRequest?) -> Void) {
        completionHandler(nil)
    }

    func webView(_ webView: WKWebView, decidePolicyFor action: WKNavigationAction, decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
        guard let url = action.request.url else { decisionHandler(.cancel); return }
        if policy.allows(url) {
            if action.shouldPerformDownload { decisionHandler(.download) }
            else if action.targetFrame == nil { webView.load(action.request); decisionHandler(.cancel) }
            else { decisionHandler(.allow) }
        } else {
            decisionHandler(.cancel)
            if action.targetFrame == nil || action.targetFrame?.isMainFrame == true {
                if ["https", "mailto", "tel"].contains(url.scheme ?? "") { UIApplication.shared.open(url) }
                else { status("Tautan ini tidak didukung aplikasi.") }
            }
        }
    }

    func webView(_ webView: WKWebView, decidePolicyFor response: WKNavigationResponse, decisionHandler: @escaping (WKNavigationResponsePolicy) -> Void) {
        guard policy.allows(response.response.url) else { decisionHandler(.cancel); return }
        let disposition = (response.response as? HTTPURLResponse)?.value(forHTTPHeaderField: "Content-Disposition") ?? ""
        decisionHandler(!response.canShowMIMEType || disposition.lowercased().hasPrefix("attachment") ? .download : .allow)
    }

    func webView(_ webView: WKWebView, didStartProvisionalNavigation navigation: WKNavigation!) { status("Memuat halaman…", loading: true) }
    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) { status("") }
    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) { navigationFailed(error) }
    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) { navigationFailed(error) }
    private func navigationFailed(_ error: Error) {
        if (error as NSError).code != NSURLErrorCancelled { status("Halaman gagal dimuat. Periksa koneksi, lalu coba lagi.", canRetry: true) }
    }
    func webViewWebContentProcessDidTerminate(_ webView: WKWebView) { status("Halaman terhenti. Pilih Coba lagi untuk melanjutkan.", canRetry: true) }

    func webView(_ webView: WKWebView, requestMediaCapturePermissionFor origin: WKSecurityOrigin, initiatedByFrame frame: WKFrameInfo, type: WKMediaCaptureType, decisionHandler: @escaping (WKPermissionDecision) -> Void) {
        let validOrigin = origin.protocol == "https" && origin.host.lowercased() == base.host?.lowercased()
            && (origin.port == 0 ? 443 : origin.port) == (base.port ?? 443)
        decisionHandler(validOrigin && policy.allows(webView.url) && type == .camera ? .prompt : .deny)
    }

    func webView(_ webView: WKWebView, navigationAction: WKNavigationAction, didBecome download: WKDownload) { begin(download) }
    func webView(_ webView: WKWebView, navigationResponse: WKNavigationResponse, didBecome download: WKDownload) { begin(download) }
    private func begin(_ download: WKDownload) {
        download.delegate = self
        status("Mengunduh dokumen…", loading: true)
    }

    func download(_ download: WKDownload, decideDestinationUsing response: URLResponse, suggestedFilename: String, completionHandler: @escaping (URL?) -> Void) {
        guard policy.allows(response.url) else { completionHandler(nil); return }
        let folder = FileManager.default.temporaryDirectory.appendingPathComponent("vpeople-" + UUID().uuidString, isDirectory: true)
        do {
            try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
            let filename = (suggestedFilename as NSString).lastPathComponent
            let safeName = filename.isEmpty || filename == "." || filename == ".." ? "dokumen" : filename
            let destination = folder.appendingPathComponent(safeName)
            downloads[ObjectIdentifier(download)] = destination
            completionHandler(destination)
        } catch { completionHandler(nil); status("Dokumen tidak dapat disimpan.") }
    }

    func download(_ download: WKDownload, willPerformHTTPRedirection response: HTTPURLResponse, newRequest request: URLRequest, decisionHandler: @escaping (WKDownload.RedirectPolicy) -> Void) {
        decisionHandler(policy.allows(request.url) ? .allow : .cancel)
    }

    func downloadDidFinish(_ download: WKDownload) {
        guard let file = downloads.removeValue(forKey: ObjectIdentifier(download)) else { return }
        status("Unduhan selesai. Pilih Simpan ke File untuk menyimpan dokumen.")
        let share = UIActivityViewController(activityItems: [file], applicationActivities: nil)
        share.popoverPresentationController?.sourceView = view
        share.popoverPresentationController?.sourceRect = CGRect(x: view.bounds.midX, y: view.bounds.maxY - view.safeAreaInsets.bottom, width: 1, height: 1)
        share.completionWithItemsHandler = { [weak self] _, completed, _, error in
            try? FileManager.default.removeItem(at: file.deletingLastPathComponent())
            self?.status(error != nil ? "Dokumen gagal dibagikan. Silakan unduh kembali." : (completed ? "Dokumen berhasil diproses." : "Penyimpanan dibatalkan."))
        }
        present(share, animated: true)
    }

    func download(_ download: WKDownload, didFailWithError error: Error, resumeData: Data?) {
        if let file = downloads.removeValue(forKey: ObjectIdentifier(download)) { try? FileManager.default.removeItem(at: file.deletingLastPathComponent()) }
        status("Unduhan gagal. Periksa koneksi atau login kembali.")
    }

    deinit { versionSession.invalidateAndCancel() }
}
