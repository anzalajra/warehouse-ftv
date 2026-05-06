!macro customInstall
  WriteRegStr HKLM "Software\Microsoft\Windows\CurrentVersion\Run" "WarehouseFTVKiosk" "$INSTDIR\${PRODUCT_FILENAME}.exe"
!macroend

!macro customUnInstall
  DeleteRegValue HKLM "Software\Microsoft\Windows\CurrentVersion\Run" "WarehouseFTVKiosk"
!macroend
