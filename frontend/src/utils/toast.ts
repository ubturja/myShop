export type ToastType = 'success' | 'error' | 'info' | 'warning';

interface ToastOptions {
  type?: ToastType;
  duration?: number;
  position?: 'top-right' | 'top-left' | 'bottom-right' | 'bottom-left';
}

const getToastStyles = (type: ToastType): string => {
  const baseStyles = 'fixed z-50 px-6 py-4 rounded-xl shadow-2xl animate-fade-in font-semibold flex items-center gap-3 max-w-md';
  
  switch (type) {
    case 'success':
      return `${baseStyles} bg-green-500 text-white`;
    case 'error':
      return `${baseStyles} bg-red-500 text-white`;
    case 'warning':
      return `${baseStyles} bg-yellow-500 text-white`;
    case 'info':
    default:
      return `${baseStyles} bg-blue-500 text-white`;
  }
};

const getPositionStyles = (position: string): string => {
  switch (position) {
    case 'top-right':
      return 'top-4 right-4';
    case 'top-left':
      return 'top-4 left-4';
    case 'bottom-left':
      return 'bottom-4 left-4';
    case 'bottom-right':
    default:
      return 'bottom-4 right-4';
  }
};

const getIcon = (type: ToastType): string => {
  switch (type) {
    case 'success':
      return '✓';
    case 'error':
      return '✕';
    case 'warning':
      return '⚠';
    case 'info':
    default:
      return 'ℹ';
  }
};

export const showToast = (message: string, options: ToastOptions = {}): void => {
  const {
    type = 'info',
    duration = 3000,
    position = 'bottom-right',
  } = options;

  // Remove existing toasts
  const existingToasts = document.querySelectorAll('.toast-notification');
  existingToasts.forEach(toast => toast.remove());

  // Create toast element
  const toast = document.createElement('div');
  toast.className = `toast-notification ${getToastStyles(type)} ${getPositionStyles(position)}`;
  
  // Add icon
  const icon = document.createElement('span');
  icon.className = 'text-2xl';
  icon.textContent = getIcon(type);
  
  // Add message
  const messageEl = document.createElement('span');
  messageEl.textContent = message;
  
  toast.appendChild(icon);
  toast.appendChild(messageEl);
  
  // Add to DOM
  document.body.appendChild(toast);

  // Remove after duration
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    setTimeout(() => toast.remove(), 300);
  }, duration);
};

// Convenience methods
export const successToast = (message: string): void => {
  showToast(message, { type: 'success' });
};

export const errorToast = (message: string): void => {
  showToast(message, { type: 'error' });
};

export const warningToast = (message: string): void => {
  showToast(message, { type: 'warning' });
};

export const infoToast = (message: string): void => {
  showToast(message, { type: 'info' });
};
