import React, { useState, useEffect } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { toast } from 'react-toastify';
import api from '../../services/api';

interface OtpVerificationProps {
  email: string;
  onSuccess?: () => void;
}

const OtpVerification: React.FC<OtpVerificationProps> = ({ email, onSuccess }) => {
  const [otp, setOtp] = useState<string[]>(Array(6).fill(''));
  const [isLoading, setIsLoading] = useState(false);
  const [countdown, setCountdown] = useState(60);
  const [canResend, setCanResend] = useState(false);
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => {
    // Start countdown for resend OTP
    if (countdown > 0) {
      const timer = setTimeout(() => setCountdown(countdown - 1), 1000);
      return () => clearTimeout(timer);
    } else {
      setCanResend(true);
    }
  }, [countdown]);

  const handleOtpChange = (element: HTMLInputElement, index: number) => {
    // Only allow numbers
    if (element.value && !/^[0-9]$/.test(element.value)) {
      return;
    }

    const newOtp = [...otp];
    newOtp[index] = element.value;
    setOtp(newOtp);

    // Auto-focus to next input
    if (element.value && index < 5) {
      const nextInput = element.nextElementSibling as HTMLInputElement;
      if (nextInput) nextInput.focus();
    }

    // Auto-submit if last digit is entered
    if (index === 5 && element.value) {
      handleSubmit();
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>, index: number) => {
    // Handle backspace
    if (e.key === 'Backspace' && !e.currentTarget.value && index > 0) {
      const prevInput = e.currentTarget.previousElementSibling as HTMLInputElement;
      if (prevInput) prevInput.focus();
    }
  };

  const handlePaste = (e: React.ClipboardEvent<HTMLInputElement>) => {
    e.preventDefault();
    const pastedData = e.clipboardData.getData('text/plain').trim();
    
    // Only allow numbers and exact length of 6
    if (/^\d{6}$/.test(pastedData)) {
      const newOtp = pastedData.split('').slice(0, 6);
      setOtp([...newOtp, ...Array(6 - newOtp.length).fill('')]);
      
      // Focus on the last input
      const inputs = document.querySelectorAll<HTMLInputElement>('.otp-input');
      const lastInput = inputs[Math.min(5, newOtp.length)];
      if (lastInput) lastInput.focus();
    }
  };

  const handleSubmit = async (e?: React.FormEvent) => {
    e?.preventDefault();
    
    const otpCode = otp.join('');
    if (otpCode.length !== 6) {
      toast.error('Please enter a valid 6-digit OTP');
      return;
    }

    setIsLoading(true);
    try {
      const response = await api.post('/auth/verify-otp', {
        email,
        otp: otpCode,
      });

      // Save token and user data
      const { access_token, user } = response.data;
      localStorage.setItem('auth_token', access_token);
      localStorage.setItem('user', JSON.stringify(user));

      toast.success('Email verified successfully!');
      
      // Call the onSuccess callback if provided, otherwise redirect
      if (onSuccess) {
        onSuccess();
      } else {
        // Redirect to the intended URL or home
        const from = location.state?.from?.pathname || '/';
        navigate(from, { replace: true });
      }
    } catch (error: any) {
      console.error('OTP verification failed:', error);
      const errorMessage = error.response?.data?.message || 'Failed to verify OTP. Please try again.';
      toast.error(errorMessage);
      
      // Clear OTP on error
      if (error.response?.status === 422) {
        setOtp(Array(6).fill(''));
        const firstInput = document.querySelector<HTMLInputElement>('.otp-input');
        if (firstInput) firstInput.focus();
      }
    } finally {
      setIsLoading(false);
    }
  };

  const handleResendOtp = async () => {
    if (!canResend) return;

    try {
      await api.post('/auth/resend-otp', { email });
      setCountdown(60);
      setCanResend(false);
      toast.success('A new OTP has been sent to your email');
    } catch (error) {
      console.error('Failed to resend OTP:', error);
      toast.error('Failed to resend OTP. Please try again.');
    }
  };

  return (
    <div className="max-w-md mx-auto p-6 bg-white rounded-lg shadow-md">
      <h2 className="text-2xl font-bold mb-6 text-center">Verify Your Email</h2>
      <p className="text-gray-600 mb-6 text-center">
        We've sent a 6-digit verification code to <span className="font-semibold">{email}</span>
      </p>
      
      <form onSubmit={handleSubmit} className="space-y-6">
        <div className="flex justify-center space-x-2 mb-6">
          {otp.map((digit, index) => (
            <input
              key={index}
              type="text"
              maxLength={1}
              value={digit}
              onChange={(e) => handleOtpChange(e.target, index)}
              onKeyDown={(e) => handleKeyDown(e, index)}
              onPaste={index === 0 ? handlePaste : undefined}
              className="otp-input w-12 h-12 text-2xl text-center border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              disabled={isLoading}
              autoFocus={index === 0}
            />
          ))}
        </div>
        
        <button
          type="submit"
          disabled={isLoading || otp.some(digit => !digit) || otp.length !== 6}
          className={`w-full py-3 px-4 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 ${
            (isLoading || otp.some(digit => !digit)) ? 'opacity-50 cursor-not-allowed' : ''
          }`}
        >
          {isLoading ? 'Verifying...' : 'Verify OTP'}
        </button>
        
        <div className="text-center mt-4">
          <p className="text-sm text-gray-600">
            Didn't receive a code?{' '}
            <button
              type="button"
              onClick={handleResendOtp}
              disabled={!canResend}
              className={`font-medium ${
                canResend ? 'text-blue-600 hover:text-blue-700' : 'text-gray-400 cursor-not-allowed'
              }`}
            >
              {canResend ? 'Resend OTP' : `Resend in ${countdown}s`}
            </button>
          </p>
        </div>
      </form>
    </div>
  );
};

export default OtpVerification;
