package com.wuninsu.biometrics

import android.app.KeyguardManager
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.util.Log
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricManager.Authenticators.BIOMETRIC_STRONG
import androidx.biometric.BiometricManager.Authenticators.BIOMETRIC_WEAK
import androidx.biometric.BiometricManager.Authenticators.DEVICE_CREDENTIAL
import androidx.biometric.BiometricPrompt
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONObject

/**
 * Biometric bridge functions.
 * Namespace: "WuninsuBiometrics.*"
 */
object BiometricsFunctions {

    /**
     * Returns: {available, biometry, reason, deviceSecure}
     */
    class Status(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val result = BiometricManager.from(activity).canAuthenticate(BIOMETRIC_STRONG)
            val keyguard = activity.getSystemService(Context.KEYGUARD_SERVICE) as? KeyguardManager

            val response = mutableMapOf<String, Any>(
                "available" to (result == BiometricManager.BIOMETRIC_SUCCESS),
                "biometry" to biometry(activity.packageManager),
                "deviceSecure" to (keyguard?.isDeviceSecure ?: false),
            )

            reason(result)?.let { response["reason"] = it }

            return BridgeResponse.success(response)
        }

        private fun reason(result: Int): String? = when (result) {
            BiometricManager.BIOMETRIC_SUCCESS -> null
            BiometricManager.BIOMETRIC_ERROR_NONE_ENROLLED -> "not_enrolled"
            BiometricManager.BIOMETRIC_ERROR_NO_HARDWARE -> "no_hardware"
            BiometricManager.BIOMETRIC_ERROR_HW_UNAVAILABLE -> "unavailable"
            else -> "unsupported"
        }

        private fun biometry(packageManager: PackageManager): String {
            val kinds = buildList {
                if (packageManager.hasSystemFeature(PackageManager.FEATURE_FINGERPRINT)) add("fingerprint")
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                    if (packageManager.hasSystemFeature(PackageManager.FEATURE_FACE)) add("face")
                    if (packageManager.hasSystemFeature(PackageManager.FEATURE_IRIS)) add("iris")
                }
            }

            return when (kinds.size) {
                0 -> "none"
                1 -> kinds.first()
                else -> "multiple"
            }
        }
    }

    /**
     * Parameters: id, event, title, subtitle?, cancel, allowDeviceCredential
     * Returns: {started: true}. The outcome is dispatched later to `event` as {success, id, error}.
     */
    class Prompt(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String
            val event = parameters["event"] as? String
                ?: return BridgeResponse.error("INVALID_PARAMETERS", "event is required")
            val title = parameters["title"] as? String ?: "Unlock"
            val subtitle = parameters["subtitle"] as? String
            val cancel = parameters["cancel"] as? String ?: "Cancel"
            val allowDeviceCredential = parameters["allowDeviceCredential"] as? Boolean ?: false

            activity.runOnUiThread {
                try {
                    val prompt = BiometricPrompt(
                        activity,
                        ContextCompat.getMainExecutor(activity),
                        object : BiometricPrompt.AuthenticationCallback() {
                            override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                                send(event, id, true, null)
                            }

                            override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                                send(event, id, false, error(errorCode))
                            }

                            // A single unrecognised finger or face: the prompt stays open for another try.
                            override fun onAuthenticationFailed() {}
                        }
                    )

                    val info = BiometricPrompt.PromptInfo.Builder()
                        .setTitle(title)
                        .apply { subtitle?.let { setSubtitle(it) } }
                        .setAllowedAuthenticators(authenticators(allowDeviceCredential))
                        .apply { if (!allowDeviceCredential) setNegativeButtonText(cancel) }
                        .build()

                    prompt.authenticate(info)
                } catch (e: Exception) {
                    Log.e(TAG, "Could not show the prompt: ${e.message}")
                    send(event, id, false, "unavailable")
                }
            }

            return BridgeResponse.success(mapOf("started" to true))
        }

        // Before Android 11, androidx.biometric cannot combine BIOMETRIC_STRONG with DEVICE_CREDENTIAL.
        private fun authenticators(allowDeviceCredential: Boolean): Int = when {
            !allowDeviceCredential -> BIOMETRIC_STRONG
            Build.VERSION.SDK_INT >= Build.VERSION_CODES.R -> BIOMETRIC_STRONG or DEVICE_CREDENTIAL
            else -> BIOMETRIC_WEAK or DEVICE_CREDENTIAL
        }

        private fun error(code: Int): String = when (code) {
            BiometricPrompt.ERROR_USER_CANCELED,
            BiometricPrompt.ERROR_NEGATIVE_BUTTON,
            BiometricPrompt.ERROR_CANCELED -> "cancelled"
            BiometricPrompt.ERROR_LOCKOUT,
            BiometricPrompt.ERROR_LOCKOUT_PERMANENT -> "lockout"
            BiometricPrompt.ERROR_NO_BIOMETRICS,
            BiometricPrompt.ERROR_NO_DEVICE_CREDENTIAL -> "not_enrolled"
            BiometricPrompt.ERROR_HW_NOT_PRESENT,
            BiometricPrompt.ERROR_HW_UNAVAILABLE -> "unavailable"
            BiometricPrompt.ERROR_TIMEOUT -> "timeout"
            else -> "failed"
        }

        private fun send(event: String, id: String?, success: Boolean, error: String?) {
            val payload = JSONObject()
                .put("success", success)
                .put("id", id ?: JSONObject.NULL)
                .put("error", error ?: JSONObject.NULL)

            NativeActionCoordinator.dispatchEvent(activity, event, payload.toString())
        }
    }

    private const val TAG = "WuninsuBiometrics"
}
