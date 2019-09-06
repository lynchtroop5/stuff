:: Copyright 2014 The Chromium Authors. All rights reserved.
:: Use of this source code is governed by a BSD-style license that can be
:: found in the LICENSE file.

:: Adds registry file for the native messaging application
REG ADD "HKLM\SOFTWARE\Mozilla\NativeMessagingHosts\clips.native.messaging.host" /ve /t REG_SZ /d "C:\app\host-manifest.json" /f
REG ADD "HKLM\SOFTWARE\Mozilla\ManagedStorage\clips.native.messaging.host" /ve /t REG_SZ /d "C:\app\host-manifest.json" /f
REG ADD "HKLM\SOFTWARE\Mozilla\PKCS11Modules\clips.native.messaging.host" /ve /t REG_SZ /d "C:\app\host-manifest.json" /f