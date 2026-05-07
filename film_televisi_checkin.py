import sys
import os
from PyQt5.QtWidgets import QApplication, QWidget, QLabel, QPushButton, QLineEdit, QVBoxLayout, QHBoxLayout
from PyQt5.QtGui import QFont
from PyQt5.QtCore import QTimer, QTime, Qt
from PyQt5.QtWidgets import QApplication, QWidget, QLabel, QPushButton, QLineEdit, QVBoxLayout, QHBoxLayout, QInputDialog
import ctypes
from ctypes import wintypes
import threading
import time

# GANTI SELURUH bagian KEYBOARD BLOCKER dengan ini: 

# ============ KEYBOARD BLOCKER ============
class KBDLLHOOKSTRUCT(ctypes.Structure):
    """Structure for low-level keyboard input event."""
    _fields_ = [
        ("vkCode", ctypes.c_ulong),
        ("scanCode", ctypes.c_ulong),
        ("flags", ctypes.c_ulong),
        ("time", ctypes.c_ulong),
        ("dwExtraInfo", ctypes.POINTER(ctypes.c_ulong))
    ]

# Load user32.dll explicitly
user32 = ctypes.windll.user32

# Define function prototypes
user32.SetWindowsHookExW.argtypes = [
    ctypes.c_int,
    ctypes.c_void_p,
    ctypes.wintypes.HMODULE,
    ctypes.wintypes.DWORD
]
user32.SetWindowsHookExW.restype = ctypes.c_void_p

user32.CallNextHookEx.argtypes = [
    ctypes.c_void_p,
    ctypes.c_int,
    ctypes.wintypes.WPARAM,
    ctypes.wintypes.LPARAM
]
user32.CallNextHookEx.restype = ctypes.c_long

user32.UnhookWindowsHookEx.argtypes = [ctypes.c_void_p]
user32.UnhookWindowsHookEx.restype = ctypes.wintypes.BOOL

user32.GetMessageW.argtypes = [
    ctypes.POINTER(ctypes.wintypes.MSG),
    ctypes.wintypes.HWND,
    ctypes.c_uint,
    ctypes.c_uint
]
user32.GetMessageW.restype = ctypes.wintypes.BOOL

# Callback type for low-level keyboard hook
LowLevelKeyboardProc = ctypes.CFUNCTYPE(
    ctypes.c_long,
    ctypes.c_int,
    ctypes.wintypes.WPARAM,
    ctypes.wintypes.LPARAM
)

class KeyboardBlocker: 
    """Block system keyboard shortcuts like Alt+Tab, Win key, etc."""
    
    WH_KEYBOARD_LL = 13
    
    # Keys to block
    VK_LWIN = 0x5B
    VK_RWIN = 0x5C
    VK_TAB = 0x09
    VK_ESCAPE = 0x1B
    VK_DELETE = 0x2E
    VK_F4 = 0x73
    VK_LALT = 0xA4
    VK_RALT = 0xA5
    VK_LCONTROL = 0xA2
    VK_RCONTROL = 0xA3
    
    def __init__(self):
        self.hooked = None
        self.block_enabled = False
        self._hook_thread = None
        self._running = False
        # Keep reference to callback to prevent garbage collection
        self._callback = LowLevelKeyboardProc(self._keyboard_proc)
    
    def _keyboard_proc(self, nCode, wParam, lParam):
        """Low-level keyboard hook callback."""
        if nCode >= 0 and self.block_enabled:
            kbd = ctypes.cast(lParam, ctypes.POINTER(KBDLLHOOKSTRUCT)).contents
            vk_code = kbd.vkCode
            
            # Block Windows keys
            if vk_code in (self.VK_LWIN, self.VK_RWIN):
                return 1
            
            # Check modifier states
            alt_pressed = (user32.GetAsyncKeyState(self.VK_LALT) & 0x8000 or 
                          user32.GetAsyncKeyState(self.VK_RALT) & 0x8000)
            ctrl_pressed = (user32.GetAsyncKeyState(self.VK_LCONTROL) & 0x8000 or
                           user32.GetAsyncKeyState(self.VK_RCONTROL) & 0x8000)
            
            # Block Alt combinations
            if alt_pressed and vk_code in (self.VK_TAB, self.VK_ESCAPE, self.VK_F4):
                return 1
            
            # Block Ctrl combinations
            if ctrl_pressed and vk_code in (self.VK_TAB, self.VK_ESCAPE):
                return 1
        
        return user32.CallNextHookEx(self.hooked, nCode, wParam, lParam)
    
    def _hook_thread_func(self):
        """Thread function to run the message loop."""
        # Use 0 for hMod to work with Python threads
        self.hooked = user32.SetWindowsHookExW(
            self.WH_KEYBOARD_LL,
            self._callback,
            0,  # Use 0 instead of GetModuleHandleW(None)
            0
        )
        
        if not self.hooked:
            error = ctypes.windll.kernel32.GetLastError()
            print(f"Failed to install keyboard hook!  Error code: {error}")
            return
        
        print("Keyboard hook installed successfully!")
        
        # Message loop
        msg = ctypes.wintypes.MSG()
        while self._running:
            bRet = user32.GetMessageW(ctypes.byref(msg), None, 0, 0)
            if bRet == 0 or bRet == -1:
                break
            user32.TranslateMessage(ctypes.byref(msg))
            user32.DispatchMessageW(ctypes.byref(msg))
        
        # Cleanup
        if self.hooked:
            user32.UnhookWindowsHookEx(self.hooked)
            self.hooked = None
            print("Keyboard hook uninstalled.")
    
    def install_hook(self):
        """Install the keyboard hook in a separate thread."""
        if self._hook_thread is None or not self._hook_thread.is_alive():
            self._running = True
            self._hook_thread = threading.Thread(target=self._hook_thread_func, daemon=True)
            self._hook_thread.start()
    
    def enable_blocking(self):
        """Enable keyboard blocking."""
        self.block_enabled = True
        print("Keyboard blocking ENABLED")
        
    def disable_blocking(self):
        """Disable keyboard blocking."""
        self.block_enabled = False
        print("Keyboard blocking DISABLED")
        
    def uninstall_hook(self):
        """Remove the keyboard hook."""
        self._running = False
        # Post quit message to break GetMessageW loop
        if self._hook_thread and self._hook_thread.is_alive():
            user32.PostThreadMessageW(self._hook_thread.ident, 0x0012, 0, 0)  # WM_QUIT
            self._hook_thread.join(timeout=2.0)
        self.hooked = None

# Global keyboard blocker instance
keyboard_blocker = KeyboardBlocker()
# ============ END KEYBOARD BLOCKER ============

class CheckInSystem(QWidget):
    def __init__(self):
        super().__init__()
        self.start_time = None  # Timer start time
        self.timer = None       # QTimer instance
        self.user_name = None   # Name of the user who checked in
        self.show_loading_screen()  # Show loading screen first

    def clear_layout(self):
        """Remove the current layout (if any) to prevent overlap."""
        if self.layout() is not None:
            # Create a temporary widget to clear the layout
            QWidget().setLayout(self.layout())

    def show_loading_screen(self):
        """Show loading screen before check-in UI."""
        # Set window flags dan geometry DULU
        self.setWindowFlags(Qt.Window | Qt.FramelessWindowHint | Qt.WindowStaysOnTopHint)
        screen = QApplication.primaryScreen().geometry()
        self.setGeometry(screen)
        
        # Set background langsung
        self.setStyleSheet("""
            QWidget {
                background:  qlineargradient(
                    x1: 0, y1: 0, x2: 1, y2: 1,
                    stop: 0 #4285F4,
                    stop: 0.5 #5E97F6,
                    stop: 1 #7BAAF7
                );
            }
        """)
        
        # SHOW DULU sebelum build layout
        self.show()
        self.showFullScreen()
        QApplication.processEvents()  # Force render
        
        # Baru build layout
        layout = QVBoxLayout()
        layout.setContentsMargins(0, 0, 0, 0)
        
        # Background container
        loading_bg = QWidget()
        loading_bg.setObjectName("loadingBg")
        loading_bg.setStyleSheet("""
            QWidget#loadingBg {
                background:  transparent;
            }
        """)
        
        loading_layout = QVBoxLayout(loading_bg)
        loading_layout.setAlignment(Qt.AlignCenter)
        
        # Icon
        icon_label = QLabel("🖥️")
        icon_label.setAlignment(Qt.AlignCenter)
        icon_label.setStyleSheet("""
            QLabel {
                font-size:  64px;
                background: transparent;
            }
        """)
        loading_layout.addWidget(icon_label)
        
        # App name
        app_name = QLabel("Film & Televisi")
        app_name.setAlignment(Qt.AlignCenter)
        app_name.setStyleSheet("""
            QLabel {
                color: white;
                font-size: 32px;
                font-weight: 600;
                font-family: 'Segoe UI', sans-serif;
                background: transparent;
                margin-top: 16px;
            }
        """)
        loading_layout.addWidget(app_name)
        
        # Subtitle
        subtitle = QLabel("Check-In System")
        subtitle.setAlignment(Qt.AlignCenter)
        subtitle.setStyleSheet("""
            QLabel {
                color: rgba(255, 255, 255, 0.8);
                font-size: 16px;
                font-family: 'Segoe UI', sans-serif;
                background: transparent;
                margin-top: 8px;
            }
        """)
        loading_layout.addWidget(subtitle)
        
        # Loading indicator
        self.loading_label = QLabel("Loading...")
        self.loading_label.setAlignment(Qt.AlignCenter)
        self.loading_label.setStyleSheet("""
            QLabel {
                color: rgba(255, 255, 255, 0.6);
                font-size: 14px;
                font-family: 'Segoe UI', sans-serif;
                background: transparent;
                margin-top: 32px;
            }
        """)
        loading_layout.addWidget(self.loading_label)
        
        layout.addWidget(loading_bg)
        self.setLayout(layout)
        
        # Animate loading text
        self._loading_dots = 0
        self._loading_timer = QTimer()
        self._loading_timer.timeout.connect(self._animate_loading)
        self._loading_timer.start(400)
        
        # Transition ke check-in UI setelah 2 detik
        QTimer.singleShot(8000, self._finish_loading)
    
    def _animate_loading(self):
        """Animate loading dots."""
        dots = "." * (self._loading_dots % 4)
        self.loading_label.setText(f"Loading{dots}")
        self._loading_dots += 1
    
    def _finish_loading(self):
        """Finish loading and show check-in UI."""
        self._loading_timer.stop()
        self.init_checkin_ui()

    def disable_taskbar(self):
        """Hide and disable the Windows taskbar."""
        try:
            # Find and hide main taskbar
            taskbar = ctypes.windll.user32.FindWindowW("Shell_TrayWnd", None)
            if taskbar:
                ctypes.windll.user32.ShowWindow(taskbar, 0)  # SW_HIDE = 0
            
            # Find and hide secondary taskbar (for multi-monitor)
            secondary = ctypes.windll.user32.FindWindowW("Shell_SecondaryTrayWnd", None)
            if secondary:
                ctypes.windll.user32.ShowWindow(secondary, 0)
                
            print("Taskbar HIDDEN")
        except Exception as e:
            print(f"Error hiding taskbar: {e}")
    
    def enable_taskbar(self):
        """Show and enable the Windows taskbar."""
        try:
            # Find and show main taskbar
            taskbar = ctypes.windll.user32.FindWindowW("Shell_TrayWnd", None)
            if taskbar: 
                ctypes.windll.user32.ShowWindow(taskbar, 9)  # SW_RESTORE = 9
            
            # Find and show secondary taskbar (for multi-monitor)
            secondary = ctypes.windll.user32.FindWindowW("Shell_SecondaryTrayWnd", None)
            if secondary: 
                ctypes.windll.user32.ShowWindow(secondary, 9)
                
            print("Taskbar SHOWN")
        except Exception as e:
            print(f"Error showing taskbar: {e}")

# CARI method init_checkin_ui() dan GANTI SELURUHNYA dengan: 
    def init_checkin_ui(self):
        """Initialize the fullscreen check-in UI with modern Google-style design."""
        self.clear_layout()
        self.setWindowTitle("Film dan Televisi Check-In")

        # Hide dulu, set flags, baru show
        self.hide()
        
        # Set window flags untuk fullscreen tanpa frame
        self.setWindowFlags(Qt.Window | Qt.FramelessWindowHint | Qt.WindowStaysOnTopHint)
        
        # Set geometry ke screen size
        screen = QApplication.primaryScreen().geometry()
        self.setGeometry(screen)
        
        # Show window
        self.show()
        self.showFullScreen()
        
        # Force update dan activate
        self.activateWindow()
        self.raise_()
        QApplication.processEvents()

        # Set background gradient (Google Blue style)
        # Reset stylesheet dari parent widget
        self.setStyleSheet("")
        
        # Main Layout
        layout_main = QVBoxLayout()
        layout_main.setContentsMargins(0, 0, 0, 0)
        layout_main.setSpacing(0)

        # Background container dengan gradient
        self.bg_container = QWidget()
        self.bg_container.setObjectName("bgContainer")
        self.bg_container.setStyleSheet("""
            QWidget#bgContainer {
                background: qlineargradient(
                    x1: 0, y1: 0, x2: 1, y2: 1,
                    stop:  0 #4285F4,
                    stop: 0.5 #5E97F6,
                    stop: 1 #7BAAF7
                );
            }
        """)
        
        bg_layout = QVBoxLayout(self.bg_container)
        bg_layout.setContentsMargins(0, 0, 0, 0)
        bg_layout.setSpacing(0)

        # Spacer atas
        bg_layout.addStretch(2)

        # Container card (white card di tengah)
        card_container = QHBoxLayout()
        card_container.addStretch(1)
        
        # Card widget
        card = QWidget()
        card.setFixedSize(450, 500)
        card.setStyleSheet("""
            QWidget {
                background-color: #FFFFFF;
                border-radius: 24px;
            }
        """)
        
        # Card layout
        card_layout = QVBoxLayout(card)
        card_layout.setContentsMargins(48, 48, 48, 48)
        card_layout.setSpacing(24)

        # Icon/Logo placeholder (circle with icon)
        icon_container = QHBoxLayout()
        icon_label = QLabel("🖥️")
        icon_label.setFixedSize(80, 80)
        icon_label.setAlignment(Qt.AlignCenter)
        icon_label.setStyleSheet("""
            QLabel {
                background-color: #E8F0FE;
                border-radius: 40px;
                font-size: 36px;
            }
        """)
        icon_container.addStretch()
        icon_container.addWidget(icon_label)
        icon_container.addStretch()
        card_layout.addLayout(icon_container)

        # Title
        self.label_title = QLabel("Selamat Datang")
        self.label_title.setAlignment(Qt.AlignCenter)
        self.label_title.setStyleSheet("""
            QLabel {
                color: #202124;
                font-size: 28px;
                font-weight: 400;
                font-family: 'Segoe UI', 'Google Sans', sans-serif;
                background:  transparent;
            }
        """)
        card_layout.addWidget(self.label_title)

        # Subtitle
        subtitle = QLabel("Silakan masukkan data Anda untuk check-in")
        subtitle.setAlignment(Qt.AlignCenter)
        subtitle.setStyleSheet("""
            QLabel {
                color: #5F6368;
                font-size: 14px;
                font-family: 'Segoe UI', 'Google Sans', sans-serif;
                background: transparent;
            }
        """)
        card_layout.addWidget(subtitle)

        card_layout.addSpacing(16)

        # Input Nama
        self.name_input = QLineEdit()
        self.name_input.setPlaceholderText("Nama Lengkap")
        self.name_input.setStyleSheet("""
            QLineEdit {
                background-color: #FFFFFF;
                border:  2px solid #DADCE0;
                border-radius: 8px;
                padding: 16px 16px;
                font-size: 16px;
                font-family: 'Segoe UI', sans-serif;
                color: #202124;
            }
            QLineEdit:focus {
                border:  2px solid #4285F4;
                background-color: #FFFFFF;
            }
            QLineEdit::placeholder {
                color:  #9AA0A6;
            }
        """)
        self.name_input.setFixedHeight(56)
        card_layout.addWidget(self.name_input)

        # Input NIM
        self.nim_input = QLineEdit()
        self.nim_input.setPlaceholderText("NIM")
        self.nim_input.setStyleSheet("""
            QLineEdit {
                background-color: #FFFFFF;
                border:  2px solid #DADCE0;
                border-radius: 8px;
                padding: 16px 16px;
                font-size: 16px;
                font-family: 'Segoe UI', sans-serif;
                color: #202124;
            }
            QLineEdit: focus {
                border: 2px solid #4285F4;
                background-color: #FFFFFF;
            }
            QLineEdit:: placeholder {
                color: #9AA0A6;
            }
        """)
        self.nim_input.setFixedHeight(56)
        card_layout.addWidget(self.nim_input)

        card_layout.addSpacing(8)

        # Check-in Button
        submit_button = QPushButton("Check-In")
        submit_button.setCursor(Qt.PointingHandCursor)
        submit_button.setStyleSheet("""
            QPushButton {
                background-color: #4285F4;
                color: white;
                border:  none;
                border-radius: 8px;
                padding: 16px 32px;
                font-size: 16px;
                font-weight: 600;
                font-family:  'Segoe UI', sans-serif;
            }
            QPushButton:hover {
                background-color:  #3367D6;
            }
            QPushButton:pressed {
                background-color: #2851A3;
            }
        """)
        submit_button.setFixedHeight(56)
        submit_button.clicked.connect(self.handle_checkin)
        card_layout.addWidget(submit_button)

        # Enter key handlers
        self.name_input.returnPressed.connect(self._on_name_enter)
        self.nim_input.returnPressed.connect(self._on_nim_enter)

        # Error message label (hidden by default)
        self.error_label = QLabel("")
        self.error_label.setAlignment(Qt.AlignCenter)
        self.error_label.setStyleSheet("""
            QLabel {
                color: #D93025;
                font-size: 13px;
                font-family: 'Segoe UI', sans-serif;
                background: transparent;
            }
        """)
        self.error_label.hide()
        card_layout.addWidget(self.error_label)

        card_layout.addStretch()

        card_container.addWidget(card)
        card_container.addStretch(1)
        bg_layout.addLayout(card_container)

        # Spacer bawah
        bg_layout.addStretch(2)

        # Bottom bar untuk Shutdown dan Admin
        bottom_bar = QHBoxLayout()
        bottom_bar.setContentsMargins(32, 16, 32, 32)

        # Admin Close Button (hidden by default)
        self.admin_close_button = QPushButton("Admin")
        self.admin_close_button.setCursor(Qt.PointingHandCursor)
        self.admin_close_button.setStyleSheet("""
            QPushButton {
                background-color: rgba(255, 255, 255, 0.2);
                color: white;
                border:  1px solid rgba(255, 255, 255, 0.3);
                border-radius: 8px;
                padding:  12px 24px;
                font-size: 14px;
                font-family: 'Segoe UI', sans-serif;
            }
            QPushButton:hover {
                background-color: rgba(255, 255, 255, 0.3);
            }
        """)
        self.admin_close_button.clicked.connect(self.admin_close_dialog)
        self.admin_close_button.hide()
        bottom_bar.addWidget(self.admin_close_button)

        bottom_bar.addStretch()

         # Shutdown button
        shutdown_button = QPushButton("Shutdown")
        shutdown_button.setCursor(Qt.PointingHandCursor)
        shutdown_button.setStyleSheet("""
            QPushButton {
                background-color:  #EA4335;
                color: white;
                border: none;
                border-radius: 8px;
                padding: 12px 24px;
                font-size: 14px;
                font-family: 'Segoe UI', sans-serif;
            }
            QPushButton:hover {
                background-color:  #D33828;
            }
            QPushButton:pressed {
                background-color: #B8291E;
            }
        """)
        shutdown_button.clicked.connect(self.shutdown_handler)
        bottom_bar.addWidget(shutdown_button)

        bg_layout.addLayout(bottom_bar)

        # Tambahkan bg_container ke layout_main
        layout_main.addWidget(self.bg_container)

        # Set the layout
        self.setLayout(layout_main)

        # Enable keyboard blocking dan hide taskbar
        keyboard_blocker.enable_blocking()
        self.disable_taskbar()


    def handle_checkin(self):
        """Handle the check-in process."""
        name = self.name_input.text().strip()
        nim = self.nim_input.text().strip()

        if not name or not nim:
            self.error_label.setText("⚠️ Mohon isi Nama dan NIM Anda")
            self.error_label.show()
            
            error_style = """
                QLineEdit {
                    background-color: #FFFFFF;
                    border: 2px solid #D93025;
                    border-radius: 8px;
                    padding: 16px 16px;
                    font-size: 16px;
                    font-family: 'Segoe UI', sans-serif;
                    color: #202124;
                }
                QLineEdit: focus {
                    border:  2px solid #D93025;
                }
            """
            if not name:
                self.name_input.setStyleSheet(error_style)
            if not nim: 
                self.nim_input.setStyleSheet(error_style)
            return

        # Store data dan langsung pindah ke timer
        self.user_name = name
        self.user_nim = nim
        
        # Langsung init timer tanpa delay
        self.init_timer_ui()

    def _on_name_enter(self):
        """Handle Enter key on name input - move focus to NIM input."""
        self.nim_input.setFocus()
    
    def _on_nim_enter(self):
        """Handle Enter key on NIM input - trigger check-in."""
        # Gunakan QTimer untuk delay sedikit agar tidak konflik
        QTimer.singleShot(50, self.handle_checkin)

    def init_timer_ui(self):
        """Initialize the Timer UI (minimizable, draggable, and resizable)."""
        # Clear layout tanpa hide/show yang tidak perlu
        self.clear_layout()
        
        # Reset stylesheet
        self.setStyleSheet("")
        
        # Set window properties
        self.setWindowTitle("Timer Aktif")
        self.setWindowFlags(Qt.FramelessWindowHint | Qt.Tool)
        self.setGeometry(100, 100, 300, 200)
        self.setMinimumSize(200, 150)
        self.setMouseTracking(True)
        
        # Resize variables
        self._resize_margin = 10
        self._resizing = False
        self._resize_direction = None
        self._is_dragging = False
        self._drag_start_pos = None
    
        # Layout
        layout = QVBoxLayout()
        layout.setContentsMargins(16, 16, 16, 16)
        layout.setSpacing(8)
    
        # User name label
        self.user_label = QLabel(f"Hi, {self.user_name}")
        self.user_label.setFont(QFont("Segoe UI", 16))
        self.user_label.setAlignment(Qt.AlignCenter)
    
        # Timer label
        self.timer_label = QLabel("00:00:00")
        self.timer_label.setFont(QFont("Segoe UI", 24))
        self.timer_label.setAlignment(Qt.AlignCenter)
    
        # Logout button
        self.logout_button = QPushButton("Logout")
        self.logout_button.setFont(QFont("Segoe UI", 12))
        self.logout_button.setCursor(Qt.PointingHandCursor)
        self.logout_button.setStyleSheet("""
            QPushButton {
                background-color: #0078d4;
                color: white;
                border: none;
                border-radius: 8px;
                padding: 8px 16px;
            }
            QPushButton:hover {
                background-color:  #005a9e;
            }
        """)
        self.logout_button.clicked.connect(self.logout_handler)

        # Admin Close button
        self.admin_close_button = QPushButton("Admin Close")
        self.admin_close_button.setFont(QFont("Segoe UI", 12))
        self.admin_close_button.setCursor(Qt.PointingHandCursor)
        self.admin_close_button.setStyleSheet("""
            QPushButton {
                background-color: #EA4335;
                color: white;
                border: none;
                border-radius: 8px;
                padding: 8px 16px;
            }
            QPushButton: hover {
                background-color: #D33828;
            }
        """)
        self.admin_close_button.clicked.connect(self.admin_close_dialog)
        self.admin_close_button.hide()
    
        # Add to layout
        layout.addWidget(self.admin_close_button, alignment=Qt.AlignCenter)
        layout.addWidget(self.user_label, alignment=Qt.AlignCenter)
        layout.addWidget(self.timer_label, alignment=Qt.AlignCenter)
        layout.addWidget(self.logout_button, alignment=Qt.AlignCenter)

        # Set layout dan show
        self.setLayout(layout)
        self.show()
        
        # Start timer
        self.start_time = QTime.currentTime()
        self.timer = QTimer(self)
        self.timer.timeout.connect(self.update_timer)
        self.timer.start(1000)
        
        # Enable taskbar dan disable keyboard blocking
        keyboard_blocker.disable_blocking()
        self.enable_taskbar()

    def keyPressEvent(self, event):
        """Detect specific key combinations for Admin functionalities."""
        # Jika Ctrl + A ditekan, tampilkan Admin Close button
        if event.modifiers() == Qt.ControlModifier and event.key() == Qt.Key_A:
            if hasattr(self, 'admin_close_button') and self.admin_close_button: 
                try:
                    self.admin_close_button.show()
                except RuntimeError: 
                    pass  # Button sudah dihapus, ignore
        # Jika Esc ditekan, sembunyikan Admin Close button
        elif event.key() == Qt.Key_Escape: 
            if hasattr(self, 'admin_close_button') and self.admin_close_button: 
                try: 
                    self.admin_close_button.hide()
                except RuntimeError:
                    pass  # Button sudah dihapus, ignore

    def admin_close_dialog(self):
        """Display a dialog for Admin PIN verification before closing the app."""
        pin, ok = QInputDialog.getText(self, "Admin Authentication", "Masukkan PIN Admin:", QLineEdit.Password)
        if ok and pin == "9999": # GANTI DENGAN PIN YANG DIINGINKAN
            # Cleanup sebelum quit
            keyboard_blocker.disable_blocking()
            keyboard_blocker.uninstall_hook()
            self.enable_taskbar()
            
            # Stop timer jika ada
            if self.timer and self.timer.isActive():
                self.timer.stop()
            
            # Quit aplikasi
            QApplication.quit()
        elif ok:
            print("PIN Salah!")

    
    def closeEvent(self, event):
        """Override the close event to prevent the window from being closed."""
        event.ignore()  # Ignore the close event, so the window won't close
    
    def mousePressEvent(self, event):
        """Initialize dragging or resizing when the mouse is pressed."""
        if event.button() == Qt.LeftButton:
            pos = event.pos()
            rect = self.rect()
            
            # Cek apakah di area resize (pinggir window)
            self._resize_direction = self._get_resize_direction(pos, rect)
            
            if self._resize_direction:
                self._resizing = True
                self._resize_start_pos = event.globalPos()
                self._resize_start_geometry = self.geometry()
            else:
                # Dragging
                self._is_dragging = True
                self._drag_start_pos = event.globalPos() - self.frameGeometry().topLeft()
            
            event.accept()
    
    def mouseMoveEvent(self, event):
        """Handle window moving or resizing during drag."""
        if self._resizing and event.buttons() == Qt.LeftButton:
            self._do_resize(event.globalPos())
            event.accept()
        elif self._is_dragging and event.buttons() == Qt.LeftButton:
            self.move(event.globalPos() - self._drag_start_pos)
            event.accept()
        else:
            # Update cursor berdasarkan posisi
            self._update_cursor(event.pos())
    
    def mouseReleaseEvent(self, event):
        """Stop dragging or resizing when the mouse is released."""
        if event.button() == Qt.LeftButton:
            self._is_dragging = False
            self._resizing = False
            self._resize_direction = None
            self.setCursor(Qt.ArrowCursor)
            event.accept()
        
    def update_timer(self):
        """Update the timer label."""
        elapsed = self.start_time.secsTo(QTime.currentTime())
        elapsed_text = QTime(0, 0).addSecs(elapsed).toString("HH:mm:ss")
        self.timer_label.setText(elapsed_text)

    def logout_handler(self):
        """Handle logout button."""
        self.timer.stop()  # Stop the timer
        self.start_time = None  # Reset timer
        self.user_name = None  # Reset user name
        
        # Hide window dulu sebelum reinitialize
        self.hide()
        
        # Re-enable keyboard blocking for check-in page
        keyboard_blocker.enable_blocking()
        self.disable_taskbar()
        
        # Gunakan QTimer untuk delay agar window state ter-reset dengan benar
        QTimer.singleShot(100, self._reinit_checkin)
    
    def _reinit_checkin(self):
        """Reinitialize check-in UI after logout."""
        # Destroy current window state completely
        self.hide()
        
        # Clear window flags dulu
        self.setWindowFlags(Qt.Widget)
        
        # Kemudian panggil init
        self.init_checkin_ui()

    def _get_resize_direction(self, pos, rect):
        """Determine resize direction based on mouse position."""
        margin = self._resize_margin if hasattr(self, '_resize_margin') else 10
        
        left = pos.x() < margin
        right = pos.x() > rect.width() - margin
        top = pos.y() < margin
        bottom = pos.y() > rect.height() - margin
        
        if top and left:
            return 'top-left'
        elif top and right: 
            return 'top-right'
        elif bottom and left:
            return 'bottom-left'
        elif bottom and right:
            return 'bottom-right'
        elif left: 
            return 'left'
        elif right:
            return 'right'
        elif top:
            return 'top'
        elif bottom: 
            return 'bottom'
        return None
    
    def _update_cursor(self, pos):
        """Update cursor based on position for resize indication."""
        if not hasattr(self, '_resize_margin'):
            return
            
        direction = self._get_resize_direction(pos, self.rect())
        
        if direction in ('left', 'right'):
            self.setCursor(Qt.SizeHorCursor)
        elif direction in ('top', 'bottom'):
            self.setCursor(Qt.SizeVerCursor)
        elif direction in ('top-left', 'bottom-right'):
            self.setCursor(Qt.SizeFDiagCursor)
        elif direction in ('top-right', 'bottom-left'):
            self.setCursor(Qt.SizeBDiagCursor)
        else:
            self.setCursor(Qt.ArrowCursor)
    
    def _do_resize(self, global_pos):
        """Perform the resize operation."""
        if not self._resize_direction:
            return
            
        diff = global_pos - self._resize_start_pos
        geo = self._resize_start_geometry
        
        min_width = self.minimumWidth()
        min_height = self.minimumHeight()
        
        new_geo = self.geometry()
        
        if 'right' in self._resize_direction:
            new_width = max(min_width, geo.width() + diff.x())
            new_geo.setWidth(new_width)
        if 'bottom' in self._resize_direction:
            new_height = max(min_height, geo.height() + diff.y())
            new_geo.setHeight(new_height)
        if 'left' in self._resize_direction:
            new_width = max(min_width, geo.width() - diff.x())
            if new_width > min_width:
                new_geo.setLeft(geo.left() + diff.x())
        if 'top' in self._resize_direction:
            new_height = max(min_height, geo.height() - diff.y())
            if new_height > min_height:
                new_geo.setTop(geo.top() + diff.y())
        
        self.setGeometry(new_geo)

    def shutdown_handler(self):
        """Shutdown the computer (for Windows 11)."""
        print("Shutdown initiated.")  # Optional log for testing
        os.system("shutdown /s /t 1")  # Shutdown computer immediately


if __name__ == "__main__":
    # Buat app dan window DULU (tanpa install hook)
    app = QApplication(sys.argv)
    window = CheckInSystem()
    
    # Install keyboard hook SETELAH window muncul (di background)
    QTimer.singleShot(100, keyboard_blocker.install_hook)
    
    result = app.exec_()
    
    # Cleanup
    keyboard_blocker.uninstall_hook()
    window.enable_taskbar()
    
    sys.exit(result)