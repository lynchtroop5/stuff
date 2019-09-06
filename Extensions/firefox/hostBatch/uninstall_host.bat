:: Copyright 2014 The Chromium Authors. All rights reserved.
:: Use of this source code is governed by a BSD-style license that can be
:: found in the LICENSE file.

:: Deletes the entry created by install_host.bat
REG DELETE "HKLM\SOFTWARE\Mozilla\NativeMessagingHosts\clips.native.messaging.host" /f
REG DELETE "HKLM\SOFTWARE\Mozilla\ManagedStorage\clips.native.messaging.host" /f
REG DELETE "HKLM\SOFTWARE\Mozilla\PKCS11Modules\clips.native.messaging.host" /f