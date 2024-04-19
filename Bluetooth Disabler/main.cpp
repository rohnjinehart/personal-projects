#include <windows.h>
#include <powrprof.h>
#include <iostream>
#pragma comment(lib, "PowrProf.lib")

// Correct callback function signature
DWORD CALLBACK PowerSettingCallback(HPOWERNOTIFY hPowerNotify, UINT dwMsg, WPARAM wParam, LPARAM lParam) {
    if (dwMsg == PBT_POWERSETTINGCHANGE) {
        PPOWERBROADCAST_SETTING pbs = (PPOWERBROADCAST_SETTING)lParam;
        if (pbs->PowerSetting == GUID_LIDSWITCH_STATE_CHANGE && pbs->DataLength == sizeof(DWORD)) {
            DWORD lidState = *(DWORD*)pbs->Data;
            if (lidState == 0) { // 0 means lid closed
                std::cout << "Lid closed detected. Turning off Bluetooth adapter..." << std::endl;
                // Replace 'Bluetooth_Device_ID' with the actual device ID of your Bluetooth adapter
                system("devcon disable Bluetooth_Device_ID");
            }
        }
    }
    return TRUE;
}

int main() {
    GUID LidSwitchStateChange = GUID_LIDSWITCH_STATE_CHANGE;

    // Register for power setting notification
    HPOWERNOTIFY hPowerNotify = RegisterPowerSettingNotification(GetCurrentProcess(), &LidSwitchStateChange, DEVICE_NOTIFY_CALLBACK_ROUTINE);

    if (!hPowerNotify) {
        std::cerr << "Failed to register for lid switch notification." << std::endl;
        return 1;
    }

    std::cout << "Monitoring lid state. Close the lid to turn off the Bluetooth adapter." << std::endl;

    MSG msg = {};
    while (GetMessage(&msg, NULL, 0, 0) != 0) {
        TranslateMessage(&msg);
        DispatchMessage(&msg);
    }

    // Cleanup
    if (hPowerNotify) {
        UnregisterPowerSettingNotification(hPowerNotify);
    }

    return 0;
}