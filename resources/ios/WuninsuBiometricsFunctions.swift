import Foundation
import LocalAuthentication

// MARK: - Biometrics Function Namespace

/// Biometric bridge functions.
/// Namespace: "WuninsuBiometrics.*"
enum WuninsuBiometricsFunctions {

    // MARK: - WuninsuBiometrics.Status

    /// Returns: {available, biometry, reason, deviceSecure}
    class Status: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let context = LAContext()
            var error: NSError?
            let available = context.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: &error)

            var response: [String: Any] = [
                "available": available,
                "biometry": WuninsuBiometricsFunctions.biometry(context.biometryType),
                "deviceSecure": LAContext().canEvaluatePolicy(.deviceOwnerAuthentication, error: nil),
            ]

            if !available {
                response["reason"] = WuninsuBiometricsFunctions.reason(error)
            }

            return BridgeResponse.success(data: response)
        }
    }

    // MARK: - WuninsuBiometrics.Prompt

    /// Parameters: id, event, title, reason, cancel, allowDeviceCredential
    /// Returns: {started: true}. The outcome is dispatched later to `event` as {success, id, error}.
    class Prompt: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let event = parameters["event"] as? String else {
                return BridgeResponse.error(code: "INVALID_PARAMETERS", message: "event is required")
            }

            let id = parameters["id"] as? String
            let title = parameters["title"] as? String ?? "Unlock"
            let reason = parameters["reason"] as? String ?? title
            let allowDeviceCredential = parameters["allowDeviceCredential"] as? Bool ?? false

            let context = LAContext()
            context.localizedCancelTitle = parameters["cancel"] as? String ?? "Cancel"

            if !allowDeviceCredential {
                // Hides the "Enter Password" button.
                context.localizedFallbackTitle = ""
            }

            let policy: LAPolicy = allowDeviceCredential
                ? .deviceOwnerAuthentication
                : .deviceOwnerAuthenticationWithBiometrics

            context.evaluatePolicy(policy, localizedReason: reason.isEmpty ? "Unlock" : reason) { success, error in
                var payload: [String: Any] = ["success": success]

                if let id = id {
                    payload["id"] = id
                }

                if !success {
                    payload["error"] = WuninsuBiometricsFunctions.promptError(error)
                }

                // Keeps the context alive until the prompt has finished.
                context.invalidate()

                DispatchQueue.main.async {
                    LaravelBridge.shared.send?(event, payload)
                }
            }

            return BridgeResponse.success(data: ["started": true])
        }
    }

    // MARK: - Helpers

    static func biometry(_ type: LABiometryType) -> String {
        switch type {
        case .faceID: return "face"
        case .touchID: return "fingerprint"
        case .opticID: return "optic"
        case .none: return "none"
        @unknown default: return "unknown"
        }
    }

    static func reason(_ error: NSError?) -> String {
        guard let error = error, error.domain == LAError.errorDomain else {
            return "unsupported"
        }

        switch LAError.Code(rawValue: error.code) {
        case .biometryNotEnrolled: return "not_enrolled"
        case .biometryNotAvailable: return "unavailable"
        case .biometryLockout: return "lockout"
        case .passcodeNotSet: return "no_passcode"
        default: return "unsupported"
        }
    }

    static func promptError(_ error: Error?) -> String {
        guard let error = error as? LAError else {
            return "failed"
        }

        switch error.code {
        case .userCancel, .appCancel, .systemCancel, .userFallback: return "cancelled"
        case .biometryLockout: return "lockout"
        case .biometryNotEnrolled, .passcodeNotSet: return "not_enrolled"
        case .biometryNotAvailable: return "unavailable"
        default: return "failed"
        }
    }
}
