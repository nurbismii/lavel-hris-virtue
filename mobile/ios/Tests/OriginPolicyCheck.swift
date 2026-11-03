import Foundation

@main
struct OriginPolicyCheck {
    static func main() {
        let policy = OriginPolicy(base: URL(string: "https://hris.example.test")!)
        assert(policy.allows(URL(string: "https://hris.example.test/login")))
        assert(policy.allows(URL(string: "https://hris.example.test:443/document")))
        for value in ["http://hris.example.test", "https://hris.example.test.evil.test",
                      "https://hris.example.test@evil.test", "https://evil@hris.example.test",
                      "file:///private", "https://hris.example.test:8443"] {
            assert(!policy.allows(URL(string: value)), value)
        }
        assert(!policy.allows(nil))
        print("OriginPolicy: passed")
    }
}
