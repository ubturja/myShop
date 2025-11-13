import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';

type FulfillmentType = 'quick_local' | 'standard' | 'group_buy';

interface CheckoutForm {
  fulfillmentType: FulfillmentType;
  // Quick Local
  latitude?: number;
  longitude?: number;
  // Standard
  shippingAddress?: string;
  shippingCity?: string;
  shippingState?: string;
  shippingZip?: string;
  // Group Buy
  groupBuyId?: number;
  // Payment
  cardNumber: string;
  cardExpiry: string;
  cardCvc: string;
  cardName: string;
}

const Checkout: React.FC = () => {
  const navigate = useNavigate();
  const [form, setForm] = useState<CheckoutForm>({
    fulfillmentType: 'standard',
    cardNumber: '',
    cardExpiry: '',
    cardCvc: '',
    cardName: '',
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setForm((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  const handleGetLocation = () => {
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          setForm((prev) => ({
            ...prev,
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
          }));
        },
        (error) => {
          alert('Failed to get location: ' + error.message);
        }
      );
    } else {
      alert('Geolocation is not supported by your browser');
    }
  };

  const validateForm = (): boolean => {
    if (form.fulfillmentType === 'quick_local') {
      if (!form.latitude || !form.longitude) {
        setError('Please provide your location for quick local delivery');
        return false;
      }
    } else if (form.fulfillmentType === 'standard') {
      if (!form.shippingAddress || !form.shippingCity || !form.shippingState || !form.shippingZip) {
        setError('Please fill in all shipping address fields');
        return false;
      }
    } else if (form.fulfillmentType === 'group_buy') {
      if (!form.groupBuyId) {
        setError('Please select a group buy');
        return false;
      }
    }

    if (!form.cardNumber || !form.cardExpiry || !form.cardCvc || !form.cardName) {
      setError('Please fill in all payment details');
      return false;
    }

    return true;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (!validateForm()) {
      return;
    }

    try {
      setLoading(true);

      let order;
      if (form.fulfillmentType === 'quick_local') {
        order = await api.checkoutQuickLocal({
          user_latitude: form.latitude!,
          user_longitude: form.longitude!,
          payment_method: 'credit_card',
        });
      } else if (form.fulfillmentType === 'standard') {
        order = await api.checkoutStandard({
          shipping_address: {
            address: form.shippingAddress!,
            city: form.shippingCity!,
            state: form.shippingState!,
            zip: form.shippingZip!,
          },
          payment_method: 'credit_card',
        });
      } else {
        order = await api.checkoutGroupBuy({
          group_buy_id: form.groupBuyId!,
          payment_method: 'credit_card',
        });
      }

      alert('Order placed successfully! Order ID: ' + order.id);
      navigate('/orders');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to place order');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <h1 className="text-3xl font-bold text-gray-900 mb-8">Checkout</h1>

      {error && (
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-8">
        {/* Fulfillment Type */}
        <div className="bg-white rounded-lg shadow-md p-6">
          <h2 className="text-xl font-bold text-gray-900 mb-4">Fulfillment Method</h2>

          <div className="space-y-3">
            <label className="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
              <input
                type="radio"
                name="fulfillmentType"
                value="quick_local"
                checked={form.fulfillmentType === 'quick_local'}
                onChange={handleInputChange}
                className="mr-3"
                data-testid="fulfillment-quick-local"
              />
              <div>
                <p className="font-semibold text-gray-900">Quick Local Delivery</p>
                <p className="text-sm text-gray-600">Get items from nearest store within hours</p>
              </div>
            </label>

            <label className="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
              <input
                type="radio"
                name="fulfillmentType"
                value="standard"
                checked={form.fulfillmentType === 'standard'}
                onChange={handleInputChange}
                className="mr-3"
                data-testid="fulfillment-standard"
              />
              <div>
                <p className="font-semibold text-gray-900">Standard Shipping</p>
                <p className="text-sm text-gray-600">Delivered to your address in 3-5 days</p>
              </div>
            </label>

            <label className="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
              <input
                type="radio"
                name="fulfillmentType"
                value="group_buy"
                checked={form.fulfillmentType === 'group_buy'}
                onChange={handleInputChange}
                className="mr-3"
                data-testid="fulfillment-group-buy"
              />
              <div>
                <p className="font-semibold text-gray-900">Group Buy</p>
                <p className="text-sm text-gray-600">Save money by buying with others</p>
              </div>
            </label>
          </div>
        </div>

        {/* Quick Local - Location */}
        {form.fulfillmentType === 'quick_local' && (
          <div className="bg-white rounded-lg shadow-md p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-4">Your Location</h2>

            <div className="space-y-4">
              <button
                type="button"
                onClick={handleGetLocation}
                className="w-full bg-blue-600 text-white px-4 py-3 rounded-md font-semibold hover:bg-blue-700 transition-colors"
                data-testid="get-location-button"
              >
                Get Current Location
              </button>

              {form.latitude && form.longitude && (
                <div className="bg-green-50 border border-green-200 rounded-md p-4">
                  <p className="text-sm text-green-800">
                    Location: {form.latitude.toFixed(6)}, {form.longitude.toFixed(6)}
                  </p>
                </div>
              )}

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Latitude
                  </label>
                  <input
                    type="number"
                    name="latitude"
                    step="any"
                    value={form.latitude || ''}
                    onChange={handleInputChange}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    placeholder="37.7749"
                    data-testid="latitude-input"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Longitude
                  </label>
                  <input
                    type="number"
                    name="longitude"
                    step="any"
                    value={form.longitude || ''}
                    onChange={handleInputChange}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    placeholder="-122.4194"
                    data-testid="longitude-input"
                  />
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Standard - Shipping Address */}
        {form.fulfillmentType === 'standard' && (
          <div className="bg-white rounded-lg shadow-md p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-4">Shipping Address</h2>

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Street Address *
                </label>
                <input
                  type="text"
                  name="shippingAddress"
                  value={form.shippingAddress || ''}
                  onChange={handleInputChange}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md"
                  placeholder="123 Main St"
                  data-testid="shipping-address-input"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    City *
                  </label>
                  <input
                    type="text"
                    name="shippingCity"
                    value={form.shippingCity || ''}
                    onChange={handleInputChange}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    placeholder="San Francisco"
                    data-testid="shipping-city-input"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    State *
                  </label>
                  <input
                    type="text"
                    name="shippingState"
                    value={form.shippingState || ''}
                    onChange={handleInputChange}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    placeholder="CA"
                    data-testid="shipping-state-input"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  ZIP Code *
                </label>
                <input
                  type="text"
                  name="shippingZip"
                  value={form.shippingZip || ''}
                  onChange={handleInputChange}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md"
                  placeholder="94102"
                  data-testid="shipping-zip-input"
                />
              </div>
            </div>
          </div>
        )}

        {/* Group Buy - Selection */}
        {form.fulfillmentType === 'group_buy' && (
          <div className="bg-white rounded-lg shadow-md p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-4">Group Buy Selection</h2>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Group Buy ID *
              </label>
              <input
                type="number"
                name="groupBuyId"
                value={form.groupBuyId || ''}
                onChange={handleInputChange}
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
                placeholder="Enter group buy ID"
                data-testid="group-buy-id-input"
              />
              <p className="text-sm text-gray-500 mt-2">
                Browse active group buys to find the ID
              </p>
            </div>
          </div>
        )}

        {/* Payment Information */}
        <div className="bg-white rounded-lg shadow-md p-6">
          <h2 className="text-xl font-bold text-gray-900 mb-4">Payment Information</h2>

          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Cardholder Name *
              </label>
              <input
                type="text"
                name="cardName"
                value={form.cardName}
                onChange={handleInputChange}
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
                placeholder="John Doe"
                data-testid="card-name-input"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Card Number *
              </label>
              <input
                type="text"
                name="cardNumber"
                value={form.cardNumber}
                onChange={handleInputChange}
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
                placeholder="4242 4242 4242 4242"
                maxLength={19}
                data-testid="card-number-input"
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Expiry Date *
                </label>
                <input
                  type="text"
                  name="cardExpiry"
                  value={form.cardExpiry}
                  onChange={handleInputChange}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md"
                  placeholder="MM/YY"
                  maxLength={5}
                  data-testid="card-expiry-input"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  CVC *
                </label>
                <input
                  type="text"
                  name="cardCvc"
                  value={form.cardCvc}
                  onChange={handleInputChange}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md"
                  placeholder="123"
                  maxLength={4}
                  data-testid="card-cvc-input"
                />
              </div>
            </div>
          </div>
        </div>

        {/* Submit Button */}
        <div className="flex gap-4">
          <button
            type="button"
            onClick={() => navigate('/cart')}
            className="flex-1 bg-gray-200 text-gray-800 px-6 py-3 rounded-md font-semibold hover:bg-gray-300 transition-colors"
          >
            Back to Cart
          </button>
          <button
            type="submit"
            disabled={loading}
            className="flex-1 bg-blue-600 text-white px-6 py-3 rounded-md font-semibold hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors"
            data-testid="place-order-button"
          >
            {loading ? 'Processing...' : 'Place Order'}
          </button>
        </div>
      </form>
    </div>
  );
};

export default Checkout;
