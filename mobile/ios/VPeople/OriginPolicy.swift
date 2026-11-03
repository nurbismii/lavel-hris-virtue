import Foundation

struct OriginPolicy {
    let base: URL
    func allows(_ url: URL?) -> Bool {
        guard let url = url else { return false }
        return url.scheme?.lowercased() == "https"
            && url.user == nil && url.password == nil
            && url.host?.lowercased() == base.host?.lowercased()
            && (url.port ?? 443) == (base.port ?? 443)
    }
}
