import React, { useState, useEffect, useRef } from 'react';
import api, { Prize, GamificationReward } from '../services/api';

const SpinWheel: React.FC = () => {
  const [prizes, setPrizes] = useState<Prize[]>([]);
  const [spinning, setSpinning] = useState(false);
  const [canSpin, setCanSpin] = useState(true);
  const [nextSpinAt, setNextSpinAt] = useState<string | null>(null);
  const [result, setResult] = useState<GamificationReward | null>(null);
  const [showResult, setShowResult] = useState(false);
  const [rotation, setRotation] = useState(0);
  const wheelRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    loadPrizesAndStatus();
  }, []);

  const loadPrizesAndStatus = async () => {
    try {
      const [prizesData, rewardsData] = await Promise.all([
        api.getPrizes(),
        api.getRewards(),
      ]);

      setPrizes(prizesData);
      setCanSpin(rewardsData.can_spin_today);
      setNextSpinAt(rewardsData.next_spin_at || null);
    } catch (err) {
      console.error('Failed to load prizes:', err);
    }
  };

  const handleSpin = async () => {
    if (!canSpin || spinning) return;

    try {
      setSpinning(true);
      setShowResult(false);
      setResult(null);

      // Call the API
      const spinResult = await api.spin();

      // Calculate target rotation
      const prizeIndex = prizes.findIndex((p) => p.label === spinResult.prize.label);
      const segmentAngle = 360 / prizes.length;
      const targetAngle = prizeIndex * segmentAngle;

      // Add multiple full rotations for effect (5-7 full spins)
      const fullRotations = Math.floor(Math.random() * 3 + 5) * 360;
      const finalRotation = fullRotations + (360 - targetAngle);

      // Animate the wheel
      setRotation(rotation + finalRotation);

      // Wait for animation to complete
      setTimeout(() => {
        setSpinning(false);
        setResult(spinResult.prize);
        setShowResult(true);
        setCanSpin(false);
        setNextSpinAt(spinResult.next_spin_at);
      }, 4000); // 4 second spin animation
    } catch (err: any) {
      setSpinning(false);
      if (err.response?.status === 429) {
        alert(err.response.data.message);
        setCanSpin(false);
        setNextSpinAt(err.response.data.next_spin_at);
      } else {
        alert('Failed to spin. Please try again.');
      }
    }
  };

  const formatTimeRemaining = (isoString: string): string => {
    const now = new Date();
    const target = new Date(isoString);
    const diff = target.getTime() - now.getTime();

    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

    if (hours > 0) {
      return `${hours}h ${minutes}m`;
    }
    return `${minutes}m`;
  };

  const getSegmentPath = (index: number, total: number): string => {
    const angle = (2 * Math.PI) / total;
    const startAngle = index * angle - Math.PI / 2;
    const endAngle = startAngle + angle;

    const x1 = 150 + 140 * Math.cos(startAngle);
    const y1 = 150 + 140 * Math.sin(startAngle);
    const x2 = 150 + 140 * Math.cos(endAngle);
    const y2 = 150 + 140 * Math.sin(endAngle);

    return `M 150 150 L ${x1} ${y1} A 140 140 0 0 1 ${x2} ${y2} Z`;
  };

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <div className="text-center mb-8">
        <h1 className="text-3xl font-bold text-gray-900 mb-2">Spin the Wheel!</h1>
        <p className="text-gray-600">Try your luck and win amazing prizes</p>
        {!canSpin && nextSpinAt && (
          <p className="text-orange-600 font-medium mt-2">
            Next spin available in: {formatTimeRemaining(nextSpinAt)}
          </p>
        )}
      </div>

      <div className="flex flex-col items-center">
        {/* Wheel Container */}
        <div className="relative mb-8">
          {/* Pointer */}
          <div className="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-2 z-20">
            <div className="w-0 h-0 border-l-[20px] border-l-transparent border-r-[20px] border-r-transparent border-t-[30px] border-t-red-600"></div>
          </div>

          {/* Wheel */}
          <div
            ref={wheelRef}
            className="relative w-[300px] h-[300px] rounded-full shadow-2xl overflow-hidden transition-transform duration-[4000ms] ease-out"
            style={{ transform: `rotate(${rotation}deg)` }}
          >
            {prizes.length > 0 && (
              <svg viewBox="0 0 300 300" className="w-full h-full">
                {prizes.map((prize, index) => (
                  <g key={index}>
                    {/* Segment */}
                    <path
                      d={getSegmentPath(index, prizes.length)}
                      fill={prize.color}
                      stroke="white"
                      strokeWidth="2"
                    />
                    {/* Text */}
                    <text
                      x="150"
                      y="150"
                      fill="white"
                      fontSize="14"
                      fontWeight="bold"
                      textAnchor="middle"
                      transform={`rotate(${
                        (360 / prizes.length) * index + 360 / prizes.length / 2
                      } 150 150) translate(0 -80)`}
                    >
                      {prize.label}
                    </text>
                  </g>
                ))}
              </svg>
            )}

            {/* Center Circle */}
            <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-16 h-16 bg-white rounded-full shadow-lg flex items-center justify-center">
              <span className="text-2xl font-bold text-gray-800">🎁</span>
            </div>
          </div>
        </div>

        {/* Spin Button */}
        <button
          onClick={handleSpin}
          disabled={!canSpin || spinning}
          className={`px-12 py-4 rounded-full text-xl font-bold text-white transition-all transform hover:scale-105 ${
            canSpin && !spinning
              ? 'bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 shadow-lg'
              : 'bg-gray-400 cursor-not-allowed'
          }`}
          data-testid="spin-button"
        >
          {spinning ? 'SPINNING...' : canSpin ? 'SPIN NOW!' : 'COME BACK TOMORROW'}
        </button>

        {/* Prize Legend */}
        <div className="mt-8 grid grid-cols-2 md:grid-cols-3 gap-4 w-full max-w-2xl">
          {prizes.map((prize, index) => (
            <div
              key={index}
              className="flex items-center gap-2 p-3 bg-white rounded-lg shadow"
            >
              <div
                className="w-6 h-6 rounded-full flex-shrink-0"
                style={{ backgroundColor: prize.color }}
              ></div>
              <div className="flex-1">
                <p className="text-sm font-semibold text-gray-900">{prize.label}</p>
                <p className="text-xs text-gray-500">{prize.probability}% chance</p>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Result Modal */}
      {showResult && result && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg p-8 max-w-md w-full text-center animate-bounce">
            <div className="mb-4">
              <div
                className="w-24 h-24 rounded-full mx-auto flex items-center justify-center text-4xl"
                style={{ backgroundColor: result.color }}
              >
                🎉
              </div>
            </div>

            <h2 className="text-3xl font-bold text-gray-900 mb-2">Congratulations!</h2>
            <p className="text-xl font-semibold mb-4" style={{ color: result.color }}>
              You won: {result.label}
            </p>

            {result.code && (
              <div className="bg-gray-100 rounded-lg p-4 mb-4">
                <p className="text-sm text-gray-600 mb-1">Your code:</p>
                <p className="text-2xl font-mono font-bold text-gray-900">{result.code}</p>
                {result.expires_at && (
                  <p className="text-xs text-gray-500 mt-2">
                    Expires: {new Date(result.expires_at).toLocaleDateString()}
                  </p>
                )}
              </div>
            )}

            {result.type === 'points' && (
              <p className="text-gray-600 mb-4">
                {result.value} points have been added to your account!
              </p>
            )}

            {result.type === 'no_prize' && (
              <p className="text-gray-600 mb-4">Better luck next time!</p>
            )}

            <button
              onClick={() => setShowResult(false)}
              className="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors"
            >
              Got it!
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default SpinWheel;
