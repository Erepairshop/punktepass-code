import UIKit
import WebKit
import AuthenticationServices
import SafariServices
import CommonCrypto

// Google OAuth handler using ASWebAuthenticationSession (Google blocks OAuth in WKWebView)
class GoogleAuthHandler: NSObject, ASWebAuthenticationPresentationContextProviding {
    static let shared = GoogleAuthHandler()
    weak var webView: WKWebView?
    weak var viewController: UIViewController?
    private var authSession: ASWebAuthenticationSession?
    private var codeVerifier: String?

    // Google OAuth Web Client ID (from Google Cloud Console)
    private let googleClientId = "645942978357-ndj7dgrapd2dgndnjf03se1p08l0o9ra.apps.googleusercontent.com"
    // Callback scheme for iOS app (reversed iOS client ID - used to catch the redirect)
    private let callbackScheme = "com.googleusercontent.apps.645942978357-1bdviltt810gutpve9vjj2kab340man6"
    // HTTPS redirect URI that points to our PHP handler
    private let redirectUri = "https://punktepass.de/app/google-callback.php"

    func presentationAnchor(for session: ASWebAuthenticationSession) -> ASPresentationAnchor {
        return viewController?.view.window ?? UIWindow()
    }

    func isGoogleOAuthURL(_ url: URL) -> Bool {
        guard let host = url.host else { return false }
        let isGoogleAuth = host.contains("accounts.google.com") &&
                          (url.absoluteString.contains("oauth") ||
                           url.absoluteString.contains("signin") ||
                           url.absoluteString.contains("ServiceLogin") ||
                           url.absoluteString.contains("v3/signin") ||
                           url.absoluteString.contains("identifier") ||
                           url.absoluteString.contains("gsi"))
        return isGoogleAuth
    }

    // Generate PKCE code verifier (random string)
    private func generateCodeVerifier() -> String {
        var buffer = [UInt8](repeating: 0, count: 32)
        _ = SecRandomCopyBytes(kSecRandomDefault, buffer.count, &buffer)
        return Data(buffer).base64EncodedString()
            .replacingOccurrences(of: "+", with: "-")
            .replacingOccurrences(of: "/", with: "_")
            .replacingOccurrences(of: "=", with: "")
    }

    // Generate PKCE code challenge from verifier
    private func generateCodeChallenge(from verifier: String) -> String {
        guard let data = verifier.data(using: .utf8) else { return "" }
        var hash = [UInt8](repeating: 0, count: Int(CC_SHA256_DIGEST_LENGTH))
        data.withUnsafeBytes {
            _ = CC_SHA256($0.baseAddress, CC_LONG(data.count), &hash)
        }
        return Data(hash).base64EncodedString()
            .replacingOccurrences(of: "+", with: "-")
            .replacingOccurrences(of: "/", with: "_")
            .replacingOccurrences(of: "=", with: "")
    }

    func startGoogleAuth(completion: @escaping (String?) -> Void) {
        // Generate PKCE verifier and challenge
        codeVerifier = generateCodeVerifier()
        let codeChallenge = generateCodeChallenge(from: codeVerifier!)

        // Build Google OAuth URL for authorization code flow with PKCE
        // Uses HTTPS redirect to our server, which then redirects to the app's custom URL scheme
        var components = URLComponents(string: "https://accounts.google.com/o/oauth2/v2/auth")!
        components.queryItems = [
            URLQueryItem(name: "client_id", value: googleClientId),
            URLQueryItem(name: "redirect_uri", value: redirectUri),
            URLQueryItem(name: "response_type", value: "code"),
            URLQueryItem(name: "scope", value: "openid email profile"),
            URLQueryItem(name: "code_challenge", value: codeChallenge),
            URLQueryItem(name: "code_challenge_method", value: "S256"),
            URLQueryItem(name: "prompt", value: "select_account")
        ]

        guard let authURL = components.url else {
            print("Google Auth: Failed to create auth URL")
            completion(nil)
            return
        }

        print("Google Auth: Starting authentication with URL: \(authURL)")

        authSession = ASWebAuthenticationSession(
            url: authURL,
            callbackURLScheme: callbackScheme
        ) { [weak self] callbackURL, error in
            guard let self = self else { return }

            if let error = error {
                print("Google Auth Error: \(error.localizedDescription)")
                completion(nil)
                return
            }

            guard let callbackURL = callbackURL else {
                print("Google Auth: No callback URL")
                completion(nil)
                return
            }

            print("Google Auth: Callback URL: \(callbackURL)")

            // Extract authorization code from URL
            let components = URLComponents(url: callbackURL, resolvingAgainstBaseURL: false)
            guard let code = components?.queryItems?.first(where: { $0.name == "code" })?.value else {
                print("Google Auth: No authorization code in callback")
                completion(nil)
                return
            }

            print("Google Auth: Got authorization code")

            // Exchange code for tokens
            self.exchangeCodeForTokens(code: code, completion: completion)
        }

        authSession?.presentationContextProvider = self
        authSession?.prefersEphemeralWebBrowserSession = false
        authSession?.start()
    }

    private func exchangeCodeForTokens(code: String, completion: @escaping (String?) -> Void) {
        guard let verifier = codeVerifier else {
            print("Google Auth: No code verifier")
            completion(nil)
            return
        }

        let tokenURL = URL(string: "https://oauth2.googleapis.com/token")!
        var request = URLRequest(url: tokenURL)
        request.httpMethod = "POST"
        request.setValue("application/x-www-form-urlencoded", forHTTPHeaderField: "Content-Type")

        let params = [
            "client_id": googleClientId,
            "code": code,
            "code_verifier": verifier,
            "grant_type": "authorization_code",
            "redirect_uri": redirectUri
        ]

        let bodyString = params.map { "\($0.key)=\($0.value.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? "")" }.joined(separator: "&")
        request.httpBody = bodyString.data(using: .utf8)

        print("Google Auth: Exchanging code for tokens...")

        URLSession.shared.dataTask(with: request) { data, response, error in
            if let error = error {
                print("Google Auth: Token exchange error: \(error.localizedDescription)")
                completion(nil)
                return
            }

            guard let data = data else {
                print("Google Auth: No data from token exchange")
                completion(nil)
                return
            }

            do {
                if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                    if let idToken = json["id_token"] as? String {
                        print("Google Auth: Got ID token (length: \(idToken.count))")
                        completion(idToken)
                    } else if let errorDesc = json["error_description"] as? String {
                        print("Google Auth: Token error: \(errorDesc)")
                        completion(nil)
                    } else {
                        print("Google Auth: Unknown token response: \(json)")
                        completion(nil)
                    }
                }
            } catch {
                print("Google Auth: JSON parse error: \(error)")
                completion(nil)
            }
        }.resume()
    }

    func injectGoogleCredential(idToken: String, into webView: WKWebView) {
        // Call the website's handleGoogleCallback function with the credential
        let js = """
        (function() {
            // Simulate Google Identity Services callback
            if (typeof handleGoogleCallback === 'function') {
                handleGoogleCallback({ credential: '\(idToken)' });
            } else {
                // Alternative: trigger AJAX directly
                jQuery.ajax({
                    url: ppvLogin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ppv_google_login',
                        nonce: ppvLogin.nonce,
                        credential: '\(idToken)',
                        device_fingerprint: ''
                    },
                    success: function(res) {
                        if (res.success) {
                            window.location.href = res.data.redirect || '/';
                        } else {
                            alert(res.data.message || 'Google Login fehlgeschlagen');
                        }
                    },
                    error: function() {
                        alert('Verbindungsfehler');
                    }
                });
            }
        })();
        """

        webView.evaluateJavaScript(js) { result, error in
            if let error = error {
                print("Google Auth JS inject error: \(error.localizedDescription)")
            } else {
                print("Google Auth: Credential injected into web page")
            }
        }
    }
}

func createWebView(container: UIView, WKSMH: WKScriptMessageHandler, WKND: WKNavigationDelegate, NSO: NSObject, VC: ViewController) -> WKWebView{

    let config = WKWebViewConfiguration()
    let userContentController = WKUserContentController()

    userContentController.add(WKSMH, name: "print")
    userContentController.add(WKSMH, name: "push-subscribe")
    userContentController.add(WKSMH, name: "push-permission-request")
    userContentController.add(WKSMH, name: "push-permission-state")
    userContentController.add(WKSMH, name: "push-token")
    userContentController.add(WKSMH, name: "native-google-login")

    // Add Google login override script for iOS native auth
    injectGoogleLoginOverride(contentController: userContentController)

    config.userContentController = userContentController

    config.limitsNavigationsToAppBoundDomains = true;
    config.allowsInlineMediaPlayback = true
    config.preferences.javaScriptCanOpenWindowsAutomatically = true
    config.preferences.setValue(true, forKey: "standalone")
    
    let webView = WKWebView(frame: calcWebviewFrame(webviewView: container, toolbarView: nil), configuration: config)
    setCustomCookie(webView: webView)

    webView.autoresizingMask = [.flexibleWidth, .flexibleHeight]
    webView.isHidden = true;
    webView.navigationDelegate = WKND
    webView.scrollView.bounces = false
    webView.scrollView.contentInsetAdjustmentBehavior = .never
    webView.allowsBackForwardNavigationGestures = true
    
    // Check if macCatalyst 16.4+ is available and if so, enable web inspector.
    // This allows the web app to be inspected using Safari Web Inspector. Supported on iOS 16.4+ and macOS 13.3+
    if #available(iOS 16.4, macOS 13.3, *) {
        webView.isInspectable = true
    }
    
    let deviceModel = UIDevice.current.model
    let osVersion = UIDevice.current.systemVersion
    webView.configuration.applicationNameForUserAgent = "Safari/604.1"
    // Use standard Safari User-Agent (removed PWAShell to avoid Google blocking)
    webView.customUserAgent = "Mozilla/5.0 (\(deviceModel); CPU \(deviceModel) OS \(osVersion.replacingOccurrences(of: ".", with: "_")) like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/\(osVersion) Mobile/15E148 Safari/604.1"

    webView.addObserver(NSO, forKeyPath: #keyPath(WKWebView.estimatedProgress), options: NSKeyValueObservingOptions.new, context: nil)
    
    #if DEBUG
    if #available(iOS 16.4, *) {
        webView.isInspectable = true
    }
    #endif
    
    return webView
}

func setAppStoreAsReferrer(contentController: WKUserContentController) {
    let scriptSource = "document.referrer = `app-info://platform/ios-store`;"
    let script = WKUserScript(source: scriptSource, injectionTime: .atDocumentEnd, forMainFrameOnly: true)
    contentController.addUserScript(script);
}

func injectGoogleLoginOverride(contentController: WKUserContentController) {
    // Override Google login button to use native iOS authentication
    let scriptSource = """
    (function() {
        // Flag to identify iOS app
        window.isPWAShellApp = true;

        // Wait for DOM and jQuery
        function setupNativeGoogleLogin() {
            if (typeof jQuery === 'undefined') {
                setTimeout(setupNativeGoogleLogin, 100);
                return;
            }

            // Override Google button click
            jQuery(document).on('click', '#ppv-google-login-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                console.log('🍎 iOS: Triggering native Google login');

                // Call native iOS handler
                if (window.webkit && window.webkit.messageHandlers && window.webkit.messageHandlers['native-google-login']) {
                    window.webkit.messageHandlers['native-google-login'].postMessage({});
                } else {
                    console.error('🍎 Native Google login handler not available');
                }

                return false;
            });

            console.log('🍎 iOS: Native Google login override installed');
        }

        // Run setup
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupNativeGoogleLogin);
        } else {
            setupNativeGoogleLogin();
        }
    })();
    """
    let script = WKUserScript(source: scriptSource, injectionTime: .atDocumentStart, forMainFrameOnly: true)
    contentController.addUserScript(script);
}

func setCustomCookie(webView: WKWebView) {
    let _platformCookie = HTTPCookie(properties: [
        .domain: rootUrl.host!,
        .path: "/",
        .name: platformCookie.name,
        .value: platformCookie.value,
        .secure: "FALSE",
        .expires: NSDate(timeIntervalSinceNow: 31556926)
    ])!

    webView.configuration.websiteDataStore.httpCookieStore.setCookie(_platformCookie)

}

func calcWebviewFrame(webviewView: UIView, toolbarView: UIToolbar?) -> CGRect{
    if ((toolbarView) != nil) {
        return CGRect(x: 0, y: toolbarView!.frame.height, width: webviewView.frame.width, height: webviewView.frame.height - toolbarView!.frame.height)
    }
    else {
        let winScene = UIApplication.shared.connectedScenes.first
        let windowScene = winScene as! UIWindowScene
        var statusBarHeight = windowScene.statusBarManager?.statusBarFrame.height ?? 0

        switch displayMode {
        case "fullscreen":
            #if targetEnvironment(macCatalyst)
                if let titlebar = windowScene.titlebar {
                    titlebar.titleVisibility = .hidden
                    titlebar.toolbar = nil
                }
            #endif
            return CGRect(x: 0, y: 0, width: webviewView.frame.width, height: webviewView.frame.height)
        default:
            #if targetEnvironment(macCatalyst)
            statusBarHeight = 29
            #endif
            let windowHeight = webviewView.frame.height - statusBarHeight
            return CGRect(x: 0, y: statusBarHeight, width: webviewView.frame.width, height: windowHeight)
        }
    }
}

extension ViewController: WKUIDelegate, WKDownloadDelegate {
    // Handle target="_blank" links and popups
    func webView(_ webView: WKWebView, createWebViewWith configuration: WKWebViewConfiguration, for navigationAction: WKNavigationAction, windowFeatures: WKWindowFeatures) -> WKWebView? {
        if let requestUrl = navigationAction.request.url,
           let requestHost = requestUrl.host {
            // Check if it's an external link that should open in Safari/Maps
            let isAllowedOrigin = allowedOrigins.first(where: { requestHost.range(of: $0) != nil }) != nil
            let isAuthOrigin = authOrigins.first(where: { requestHost.range(of: $0) != nil }) != nil

            if !isAllowedOrigin && !isAuthOrigin {
                // External link - open in Safari or native app
                if ["http", "https"].contains(requestUrl.scheme?.lowercased() ?? "") {
                    // Check if Maps app can handle this URL
                    if requestHost.contains("maps.google.com") || requestHost.contains("maps.apple.com") {
                        // Try to open in Maps app first
                        if let mapsUrl = URL(string: requestUrl.absoluteString.replacingOccurrences(of: "https://", with: "maps://").replacingOccurrences(of: "http://", with: "maps://")) {
                            if UIApplication.shared.canOpenURL(mapsUrl) {
                                UIApplication.shared.open(mapsUrl)
                                return nil
                            }
                        }
                    }
                    // Fallback to Safari
                    let safariViewController = SFSafariViewController(url: requestUrl)
                    self.present(safariViewController, animated: true, completion: nil)
                } else if UIApplication.shared.canOpenURL(requestUrl) {
                    UIApplication.shared.open(requestUrl)
                }
                return nil
            }
        }

        // Internal or auth links - load in main webview
        if (navigationAction.targetFrame == nil) {
            webView.load(navigationAction.request)
        }
        return nil
    }
    // restrict navigation to target host, open external links in 3rd party apps
    func webView(_ webView: WKWebView, decidePolicyFor navigationAction: WKNavigationAction, decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
        if (navigationAction.request.url?.scheme == "about") {
            return decisionHandler(.allow)
        }
        if (navigationAction.shouldPerformDownload || navigationAction.request.url?.scheme == "blob") {
            return decisionHandler(.download)
        }

        if let requestUrl = navigationAction.request.url{
            // Handle Google OAuth with ASWebAuthenticationSession (Google blocks OAuth in WKWebView)
            if GoogleAuthHandler.shared.isGoogleOAuthURL(requestUrl) {
                decisionHandler(.cancel)
                GoogleAuthHandler.shared.webView = webView
                GoogleAuthHandler.shared.viewController = self

                // Start native Google OAuth flow
                GoogleAuthHandler.shared.startGoogleAuth { [weak self] idToken in
                    DispatchQueue.main.async {
                        if let token = idToken {
                            // Inject the credential into the web page
                            GoogleAuthHandler.shared.injectGoogleCredential(idToken: token, into: webView)
                        }
                        // Hide toolbar if visible
                        if let self = self, !self.toolbarView.isHidden {
                            self.toolbarView.isHidden = true
                            webView.frame = calcWebviewFrame(webviewView: self.webviewView, toolbarView: nil)
                        }
                    }
                }
                return
            }

            if let requestHost = requestUrl.host {
                // NOTE: Match auth origin first, because host origin may be a subset of auth origin and may therefore always match
                let matchingAuthOrigin = authOrigins.first(where: { requestHost.range(of: $0) != nil })
                if (matchingAuthOrigin != nil) {
                    decisionHandler(.allow)
                    if (toolbarView.isHidden) {
                        toolbarView.isHidden = false
                        webView.frame = calcWebviewFrame(webviewView: webviewView, toolbarView: toolbarView)
                    }
                    return
                }

                let matchingHostOrigin = allowedOrigins.first(where: { requestHost.range(of: $0) != nil })
                if (matchingHostOrigin != nil) {
                    // Open in main webview
                    decisionHandler(.allow)
                    if (!toolbarView.isHidden) {
                        toolbarView.isHidden = true
                        webView.frame = calcWebviewFrame(webviewView: webviewView, toolbarView: nil)
                    }
                    return
                }
                if (navigationAction.navigationType == .other &&
                    navigationAction.value(forKey: "syntheticClickType") as! Int == 0 &&
                    (navigationAction.targetFrame != nil) &&
                    // no error here, fake warning
                    (navigationAction.sourceFrame != nil)
                ) {
                    decisionHandler(.allow)
                    return
                }
                else {
                    decisionHandler(.cancel)
                }


                if ["http", "https"].contains(requestUrl.scheme?.lowercased() ?? "") {
                    // Can open with SFSafariViewController
                    let safariViewController = SFSafariViewController(url: requestUrl)
                    self.present(safariViewController, animated: true, completion: nil)
                } else {
                    // Scheme is not supported or no scheme is given, use openURL
                    if (UIApplication.shared.canOpenURL(requestUrl)) {
                        UIApplication.shared.open(requestUrl)
                    }
                }
            } else {
                decisionHandler(.cancel)
                if (navigationAction.request.url?.scheme == "tel" || navigationAction.request.url?.scheme == "mailto" ){
                    if (UIApplication.shared.canOpenURL(requestUrl)) {
                        UIApplication.shared.open(requestUrl)
                    }
                }
                else {
                    if requestUrl.isFileURL {
                        // not tested
                        downloadAndOpenFile(url: requestUrl.absoluteURL)
                    }
                    // if (requestUrl.absoluteString.contains("base64")){
                    //     downloadAndOpenBase64File(base64String: requestUrl.absoluteString)
                    // }
                }
            }
        }
        else {
            decisionHandler(.cancel)
        }

    }
    // Handle javascript: `window.alert(message: String)`
    func webView(_ webView: WKWebView,
        runJavaScriptAlertPanelWithMessage message: String,
        initiatedByFrame frame: WKFrameInfo,
        completionHandler: @escaping () -> Void) {

        // Set the message as the UIAlertController message
        let alert = UIAlertController(
            title: nil,
            message: message,
            preferredStyle: .alert
        )

        // Add a confirmation action “OK”
        let okAction = UIAlertAction(
            title: "OK",
            style: .default,
            handler: { _ in
                // Call completionHandler
                completionHandler()
            }
        )
        alert.addAction(okAction)

        // Display the NSAlert
        present(alert, animated: true, completion: nil)
    }
    // Handle javascript: `window.confirm(message: String)`
    func webView(_ webView: WKWebView,
        runJavaScriptConfirmPanelWithMessage message: String,
        initiatedByFrame frame: WKFrameInfo,
        completionHandler: @escaping (Bool) -> Void) {

        // Set the message as the UIAlertController message
        let alert = UIAlertController(
            title: nil,
            message: message,
            preferredStyle: .alert
        )

        // Add a confirmation action “Cancel”
        let cancelAction = UIAlertAction(
            title: "Cancel",
            style: .cancel,
            handler: { _ in
                // Call completionHandler
                completionHandler(false)
            }
        )

        // Add a confirmation action “OK”
        let okAction = UIAlertAction(
            title: "OK",
            style: .default,
            handler: { _ in
                // Call completionHandler
                completionHandler(true)
            }
        )
        alert.addAction(cancelAction)
        alert.addAction(okAction)

        // Display the NSAlert
        present(alert, animated: true, completion: nil)
    }
    // Handle javascript: `window.prompt(prompt: String, defaultText: String?)`
    func webView(_ webView: WKWebView,
        runJavaScriptTextInputPanelWithPrompt prompt: String,
        defaultText: String?,
        initiatedByFrame frame: WKFrameInfo,
        completionHandler: @escaping (String?) -> Void) {

        // Set the message as the UIAlertController message
        let alert = UIAlertController(
            title: nil,
            message: prompt,
            preferredStyle: .alert
        )

        // Add a confirmation action “Cancel”
        let cancelAction = UIAlertAction(
            title: "Cancel",
            style: .cancel,
            handler: { _ in
                // Call completionHandler
                completionHandler(nil)
            }
        )

        // Add a confirmation action “OK”
        let okAction = UIAlertAction(
            title: "OK",
            style: .default,
            handler: { _ in
                // Call completionHandler with Alert input
                if let input = alert.textFields?.first?.text {
                    completionHandler(input)
                }
            }
        )

        alert.addTextField { textField in
            textField.placeholder = defaultText
        }
        alert.addAction(cancelAction)
        alert.addAction(okAction)

        // Display the NSAlert
        present(alert, animated: true, completion: nil)
    }

    func downloadAndOpenFile(url: URL){

        let destinationFileUrl = url
        let sessionConfig = URLSessionConfiguration.default
        let session = URLSession(configuration: sessionConfig)
        let request = URLRequest(url:url)
        let task = session.downloadTask(with: request) { (tempLocalUrl, response, error) in
            if let tempLocalUrl = tempLocalUrl, error == nil {
                if let statusCode = (response as? HTTPURLResponse)?.statusCode {
                    print("Successfully download. Status code: \(statusCode)")
                }
                do {
                    try FileManager.default.copyItem(at: tempLocalUrl, to: destinationFileUrl)
                    self.openFile(url: destinationFileUrl)
                } catch (let writeError) {
                    print("Error creating a file \(destinationFileUrl) : \(writeError)")
                }
            } else {
                print("Error took place while downloading a file. Error description: \(error?.localizedDescription ?? "N/A") ")
            }
        }
        task.resume()
    }

    // func downloadAndOpenBase64File(base64String: String) {
    //     // Split the base64 string to extract the data and the file extension
    //     let components = base64String.components(separatedBy: ";base64,")

    //     // Make sure the base64 string has the correct format
    //     guard components.count == 2, let format = components.first?.split(separator: "/").last else {
    //         print("Invalid base64 string format")
    //         return
    //     }

    //     // Remove the data type prefix to get the base64 data
    //     let dataString = components.last!

    //     if let imageData = Data(base64Encoded: dataString) {
    //         let documentsUrl: URL  =  FileManager.default.urls(for: .documentDirectory, in: .userDomainMask).first!
    //         let destinationFileUrl = documentsUrl.appendingPathComponent("image.\(format)")

    //         do {
    //             try imageData.write(to: destinationFileUrl)
    //             self.openFile(url: destinationFileUrl)
    //         } catch {
    //             print("Error writing image to file url: \(destinationFileUrl): \(error)")
    //         }
    //     }
    // }

    func openFile(url: URL) {
        self.documentController = UIDocumentInteractionController(url: url)
        self.documentController?.delegate = self
        self.documentController?.presentPreview(animated: true)
    }

    func webView(_ webView: WKWebView, navigationAction: WKNavigationAction, didBecome download: WKDownload) {
        download.delegate = self
    }

    func download(_ download: WKDownload, decideDestinationUsing response: URLResponse,
                suggestedFilename: String,
                completionHandler: @escaping (URL?) -> Void) {

        let documentsPath = FileManager.default.urls(for: .documentDirectory, in: .userDomainMask)[0]
        let fileURL = documentsPath.appendingPathComponent(suggestedFilename)

        // Remove existing file if it exists, otherwise it may show an old file/content just by having the same name.
        if FileManager.default.fileExists(atPath: fileURL.path) {
            try? FileManager.default.removeItem(at: fileURL)
        }

        self.openFile(url: fileURL)
        completionHandler(fileURL)
    }
}
