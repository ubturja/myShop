import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api, { GroupBuy } from '../services/api';

const GroupBuyList: React.FC = () => {
  const [groupBuys, setGroupBuys] = useState<GroupBuy[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState<'all' | 'active' | 'fulfilled' | 'failed'>('active');

  useEffect(() => {
    loadGroupBuys();
  }, [filter]);

  const loadGroupBuys = async () => {
    try {
      setLoading(true);
      setError(null);
      const params: any = {};
      if (filter !== 'all') {
        params.status = filter.toUpperCase();
      }
      const data = await api.getGroupBuys(params);
      setGroupBuys(Array.isArray(data) ? data : []);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to load group buys');
      setGroupBuys([]); // Set to empty array on error
    } finally {
      setLoading(false);
    }
  };

  const handleJoin = async (groupBuyId: number) => {
    try {
      await api.joinGroupBuy(groupBuyId);
      alert('Successfully joined group buy!');
      loadGroupBuys();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to join group buy');
    }
  };

  const handleLeave = async (groupBuyId: number) => {
    if (!confirm('Are you sure you want to leave this group buy?')) return;

    try {
      await api.leaveGroupBuy(groupBuyId);
      alert('Successfully left group buy');
      loadGroupBuys();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to leave group buy');
    }
  };

  const formatPrice = (cents: number): string => {
    return `$${(cents / 100).toFixed(2)}`;
  };

  const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diff = date.getTime() - now.getTime();
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const days = Math.floor(hours / 24);

    if (diff < 0) return 'Expired';
    if (hours < 1) return 'Less than 1 hour';
    if (hours < 24) return `${hours} hours`;
    return `${days} days`;
  };

  const getStatusColor = (status: string): string => {
    switch (status) {
      case 'ACTIVE':
        return 'bg-green-100 text-green-800';
      case 'FULFILLED':
        return 'bg-blue-100 text-blue-800';
      case 'FAILED':
        return 'bg-red-100 text-red-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <div className="flex justify-between items-center mb-8">
        <h1 className="text-3xl font-bold text-gray-900">Group Buys</h1>
      </div>

      {/* Filter Tabs */}
      <div className="mb-6 border-b border-gray-200">
        <nav className="-mb-px flex space-x-8">
          {[
            { key: 'active', label: 'Active' },
            { key: 'fulfilled', label: 'Fulfilled' },
            { key: 'failed', label: 'Failed' },
            { key: 'all', label: 'All' },
          ].map((tab) => (
            <button
              key={tab.key}
              onClick={() => setFilter(tab.key as any)}
              className={`py-4 px-1 border-b-2 font-medium text-sm ${
                filter === tab.key
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
              data-testid={`filter-${tab.key}`}
            >
              {tab.label}
            </button>
          ))}
        </nav>
      </div>

      {error && (
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
          {error}
        </div>
      )}

      {!groupBuys || groupBuys.length === 0 ? (
        <div className="text-center py-12">
          <svg
            className="mx-auto h-12 w-12 text-gray-400 mb-4"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
            />
          </svg>
          <h2 className="text-xl font-semibold text-gray-900 mb-2">No group buys found</h2>
          <p className="text-gray-600 mb-6">
            {filter === 'active'
              ? 'There are no active group buys at the moment'
              : `No ${filter} group buys`}
          </p>
          <Link
            to="/products"
            className="inline-block bg-blue-600 text-white px-6 py-3 rounded-md font-semibold hover:bg-blue-700 transition-colors"
          >
            Browse Products
          </Link>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {groupBuys.map((groupBuy) => (
            <div
              key={groupBuy.id}
              className="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-shadow"
              data-testid={`group-buy-card-${groupBuy.id}`}
            >
              {/* Product Image */}
              <Link to={`/products/${groupBuy.product_id}`}>
                <div className="h-48 bg-gray-200 overflow-hidden">
                  {groupBuy.product?.image_url ? (
                    <img
                      src={groupBuy.product.image_url}
                      alt={groupBuy.product.title}
                      className="w-full h-full object-cover hover:scale-105 transition-transform"
                    />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center bg-gray-300">
                      <span className="text-gray-500">No Image</span>
                    </div>
                  )}
                </div>
              </Link>

              <div className="p-4">
                {/* Status Badge */}
                <div className="flex justify-between items-start mb-3">
                  <Link
                    to={`/products/${groupBuy.product_id}`}
                    className="font-bold text-gray-900 hover:text-blue-600 line-clamp-2"
                  >
                    {groupBuy.product?.title || 'Unknown Product'}
                  </Link>
                  <span
                    className={`px-2 py-1 text-xs font-medium rounded ${getStatusColor(
                      groupBuy.status
                    )}`}
                  >
                    {groupBuy.status}
                  </span>
                </div>

                {/* Progress Bar */}
                <div className="mb-3">
                  <div className="flex justify-between text-sm text-gray-600 mb-1">
                    <span>
                      {groupBuy.member_count || 0} / {groupBuy.target_size} members
                    </span>
                    <span>{groupBuy.progress_percentage || 0}%</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2">
                    <div
                      className="bg-purple-600 h-2 rounded-full transition-all"
                      style={{ width: `${groupBuy.progress_percentage || 0}%` }}
                    ></div>
                  </div>
                </div>

                {/* Pricing */}
                <div className="mb-3 space-y-1">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Team Price:</span>
                    <span className="text-lg font-bold text-green-600">
                      {formatPrice(groupBuy.team_price_cents)}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Solo Price:</span>
                    <span className="text-sm text-gray-400 line-through">
                      {formatPrice(groupBuy.product?.price_cents || 0)}
                    </span>
                  </div>
                  {groupBuy.savings && (
                    <div className="flex justify-between items-center">
                      <span className="text-sm text-gray-600">You Save:</span>
                      <span className="text-sm font-bold text-blue-600">
                        {formatPrice(groupBuy.savings)}
                      </span>
                    </div>
                  )}
                </div>

                {/* Time Remaining */}
                <p className="text-sm text-gray-600 mb-4">
                  <span className="font-medium">Expires in:</span> {formatDate(groupBuy.expires_at)}
                </p>

                {/* Action Buttons */}
                {groupBuy.status === 'ACTIVE' && (
                  <div className="space-y-2">
                    <button
                      onClick={() => handleJoin(groupBuy.id)}
                      className="w-full bg-purple-600 text-white px-4 py-2 rounded-md font-medium hover:bg-purple-700 transition-colors"
                      data-testid={`join-button-${groupBuy.id}`}
                    >
                      Join Group Buy
                    </button>
                    {/* Note: In real app, check if user is already a member */}
                    <button
                      onClick={() => handleLeave(groupBuy.id)}
                      className="w-full bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors"
                      data-testid={`leave-button-${groupBuy.id}`}
                    >
                      Leave Group
                    </button>
                  </div>
                )}

                {groupBuy.status === 'FULFILLED' && (
                  <div className="bg-blue-50 border border-blue-200 rounded-md p-3 text-center">
                    <p className="text-sm text-blue-800 font-medium">
                      ✓ Successfully fulfilled!
                    </p>
                  </div>
                )}

                {groupBuy.status === 'FAILED' && (
                  <div className="bg-red-50 border border-red-200 rounded-md p-3 text-center">
                    <p className="text-sm text-red-800 font-medium">
                      ✗ Failed to reach target
                    </p>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default GroupBuyList;
