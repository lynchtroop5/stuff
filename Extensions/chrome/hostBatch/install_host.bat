:: Copyright 2014 The Chromium Authors. All rights reserved.
:: Use of this source code is governed by a BSD-style license that can be
:: found in the LICENSE file.

:: Adds registry file for the native messaging application
REG ADD "HKLM\Software\Google\Chrome\NativeMessagingHosts\clips.native.messaging.host" /ve /t REG_SZ /d "C:\chromeApp\host-manifest.json" /f