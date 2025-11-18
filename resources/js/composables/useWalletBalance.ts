import { ref, computed, onMounted } from 'vue';
import axios from '@/lib/axios';
import { usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import type { User } from '@/types';
import CheckWalletBalanceController from '@/actions/App/Http/Controllers/CheckWalletBalanceController';

// Debug flag - set to false to suppress console logs
const DEBUG = true; // Temporarily enabled to debug real-time updates

export function useWalletBalance(type?: string) {
    const balance = ref<number | null>(null);
    const currency = ref<string | null>(null);
    const walletType = ref<string>(type || 'default');
    const updatedAt = ref<string | null>(null);
    const status = ref<'idle' | 'loading' | 'success' | 'error'>('idle');
    const message = ref<string>('');
    const realtimeNote = ref<string>(''); // for balance updated event message
    const realtimeTime = ref<string>(''); // for updated datetime string
    
    const page = usePage();
    const user = page.props.auth.user as User;
    if (DEBUG) {
        console.log('[useWalletBalance] User data:', {
            user,
            wallet: user.wallet,
            walletId: user.wallet?.id
        });
    }
    const userWalletId = user.wallet?.id;

    const fetchBalance = async () => {
        status.value = 'loading';
        message.value = 'Fetching balance…';
        if (DEBUG) console.log('[useWalletBalance] Fetching balance...', { type });
        
        try {
            const url = CheckWalletBalanceController.url();
            const { data } = await axios.get(url, {
                params: type ? { type } : {},
            });

            balance.value = data.balance;
            currency.value = data.currency;
            walletType.value = data.type;
            status.value = 'success';
            message.value = '';
            updatedAt.value = data.datetime;
            if (DEBUG) console.log('[useWalletBalance] Balance fetched successfully:', data);
        } catch (e: any) {
            status.value = 'error';
            message.value = e.response?.data?.message || 'Failed to fetch balance.';
            console.error('[useWalletBalance] Failed to fetch balance:', e);
        }
    };

    // Fetch on mount and set up Echo listener
    onMounted(() => {
        if (DEBUG) {
            console.log('[useWalletBalance] Component mounted, setting up Echo listener', {
                userId: user.id,
                userWalletId,
                channel: `App.Models.User.${user.id}`,
                event: '.balance.updated'
            });
        }
        
        fetchBalance();

        // Subscribe to user balance update event (filtered by wallet ID)
        const { listen } = useEcho<{
            walletId: number;
            balanceFloat: number;
            updatedAt: string;
            message: string;
        }>(
            `App.Models.User.${user.id}`,
            '.balance.updated',
            (event) => {
                if (DEBUG) {
                    console.log('[useWalletBalance] Echo broadcast received:', {
                        event,
                        eventWalletId: event.walletId,
                        userWalletId,
                        user: user,
                        userWallet: user.wallet,
                        matches: event.walletId === userWalletId
                    });
                }
                
                if (event.walletId !== userWalletId) {
                    if (DEBUG) console.log('[useWalletBalance] Ignoring event - wallet ID mismatch');
                    return;
                }

                if (DEBUG) console.log('[useWalletBalance] Updating balance from Echo event');
                balance.value = event.balanceFloat;
                updatedAt.value = event.updatedAt;
                realtimeNote.value = event.message;
                realtimeTime.value = event.updatedAt;

                if (DEBUG) {
                    console.log('[useWalletBalance] Balance updated via Echo:', {
                        balance: event.balanceFloat,
                        updatedAt: event.updatedAt,
                        message: event.message,
                    });
                }
            }
        );

        listen();
        if (DEBUG) console.log('[useWalletBalance] Echo listener started');
    });

    // Computed formatted balance
    const formattedBalance = computed(() =>
        balance.value !== null
            ? new Intl.NumberFormat('en-PH', {
                  style: 'currency',
                  currency: currency.value || 'PHP',
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
              }).format(balance.value)
            : '₱0.00'
    );

    return {
        balance,
        currency,
        walletType,
        status,
        message,
        updatedAt,
        realtimeNote,
        realtimeTime,
        fetchBalance,
        formattedBalance,
    };
}
