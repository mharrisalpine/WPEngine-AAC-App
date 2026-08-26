import React, { createContext, useEffect, useState, useCallback, useMemo, useRef } from 'react';
import { useToast } from '@/components/ui/use-toast';
import {
  changeMemberPassword,
  getCurrentMember,
  loginMember,
  logoutMember,
  registerMember,
  updateMemberProfile,
} from '@/lib/memberApi';
import { setAuthToken } from '@/lib/apiClient';
import { getAppRuntimeConfig } from '@/lib/backendConfig';
import { normalizeAccountInfo } from '@/lib/memberProfile';

export const AuthContext = createContext(undefined);

export const AuthProvider = ({ children }) => {
  const { toast } = useToast();
  const authRequestRef = useRef(0);
  const initialAuthRef = useRef(getAppRuntimeConfig().initialAuth || null);
  const initialAuth = initialAuthRef.current;

  const [user, setUser] = useState(() => initialAuth?.user ?? null);
  const [session, setSession] = useState(() => initialAuth?.session ?? (initialAuth?.user ? { user: initialAuth.user } : null));
  const [profile, setProfile] = useState(() => initialAuth?.profile ?? null);
  const [loading, setLoading] = useState(() => !initialAuth?.user);
  const [authReady, setAuthReady] = useState(() => Boolean(initialAuth?.user));

  const applyAuthState = useCallback((data) => {
    const nextUser = data?.user ?? null;
    setUser(nextUser);
    setSession(data?.session ?? (nextUser ? { user: nextUser } : null));
    setProfile(data?.profile ?? null);
  }, []);

  const beginAuthRequest = useCallback(() => {
    authRequestRef.current += 1;
    return authRequestRef.current;
  }, []);

  const isLatestAuthRequest = useCallback((requestId) => authRequestRef.current === requestId, []);

  const refreshProfile = useCallback(async (options = {}) => {
    const showLoading = options?.showLoading !== false;
    const requestId = beginAuthRequest();
    if (showLoading) {
      setLoading(true);
    }
    try {
      const data = await getCurrentMember();
      if (!isLatestAuthRequest(requestId)) {
        return { data: null, error: null };
      }

      applyAuthState(data);
      return { data, error: null };
    } catch (error) {
      if (!isLatestAuthRequest(requestId)) {
        return { data: null, error: null };
      }

      const isLoggedOutState =
        error.status === 401 ||
        (error.status === 403 && error?.payload?.code === 'rest_cookie_invalid_nonce') ||
        error.name === 'AbortError';

      if (isLoggedOutState) {
        setAuthToken(null);
        applyAuthState(null);
        return { data: null, error: null };
      }

      console.error('Error fetching member session:', error);
      toast({
        variant: 'destructive',
        title: 'Authentication Error',
        description: error.message || 'There was an issue loading your member profile.',
      });
      return { data: null, error };
    } finally {
      if (isLatestAuthRequest(requestId)) {
        setLoading(false);
        setAuthReady(true);
      }
    }
  }, [applyAuthState, beginAuthRequest, isLatestAuthRequest, toast]);

  useEffect(() => {
    refreshProfile({ showLoading: !initialAuthRef.current?.user });
  }, [refreshProfile]);

  const signUp = useCallback(async (email, password, options) => {
    const requestId = beginAuthRequest();
    setLoading(true);
    try {
      const data = await registerMember(email, password, options);
      if (!isLatestAuthRequest(requestId)) {
        return { user: null, error: null };
      }

      applyAuthState(data);
      toast({
        title: 'Welcome!',
        description: data?.requires_email_verification
          ? 'Your account was created. Please verify your email before logging in.'
          : 'Your account was created successfully.',
      });
      return { user: data?.user ?? null, error: null };
    } catch (error) {
      toast({
        variant: 'destructive',
        title: 'Sign up Failed',
        description: error.message || 'Something went wrong',
      });
      return { user: null, error };
    } finally {
      if (isLatestAuthRequest(requestId)) {
        setLoading(false);
      }
    }
  }, [applyAuthState, beginAuthRequest, isLatestAuthRequest, toast]);

  /**
   * Register, then PATCH profile before swapping to the main app (avoids Login unmounting mid-flow).
   */
  const signUpWithProfile = useCallback(async (email, password, registerOpts, { account_info, profile_info }) => {
    const requestId = beginAuthRequest();
    setLoading(true);
    try {
      const data = await registerMember(email, password, registerOpts);
      if (!isLatestAuthRequest(requestId)) {
        return { error: null };
      }

      applyAuthState(data);

      const baseAcc = data?.profile?.account_info || {};
      const baseProf = data?.profile?.profile_info || {};
      await updateMemberProfile({
        account_info: { ...baseAcc, ...account_info },
        profile_info: { ...baseProf, ...profile_info },
      });

      const merged = await getCurrentMember();
      applyAuthState(merged);

      toast({
        title: 'Welcome!',
        description: data?.requires_email_verification
          ? 'Your account was created. Please verify your email before logging in.'
          : 'Your account was created successfully.',
      });
      return { user: data?.user ?? null, error: null };
    } catch (error) {
      toast({
        variant: 'destructive',
        title: 'Sign up Failed',
        description: error.message || 'Something went wrong',
      });
      return { error };
    } finally {
      if (isLatestAuthRequest(requestId)) {
        setLoading(false);
      }
    }
  }, [applyAuthState, beginAuthRequest, isLatestAuthRequest, toast]);

  const signIn = useCallback(async (email, password, options = {}) => {
    const requestId = beginAuthRequest();
    setLoading(true);
    try {
      const data = await loginMember(email, password);
      if (!isLatestAuthRequest(requestId)) {
        return { user: null, error: null };
      }

      applyAuthState(data);
      return { error: null };
    } catch (error) {
      if (!isLatestAuthRequest(requestId)) {
        return { error: null };
      }

      if (error?.status === 401) {
        applyAuthState(null);
      }

      if (!options?.suppressToast) {
        toast({
          variant: 'destructive',
          title: 'Sign in Failed',
          description:
            error?.status === 401
              ? 'Incorrect email or password. Please try again.'
              : (error.message || 'We could not sign you in right now.'),
        });
      }
      return { user: null, error };
    } finally {
      if (isLatestAuthRequest(requestId)) {
        setLoading(false);
      }
    }
  }, [applyAuthState, beginAuthRequest, isLatestAuthRequest, toast]);

  const signOut = useCallback(async () => {
    const requestId = beginAuthRequest();
    setLoading(true);
    try {
      await logoutMember();
      if (!isLatestAuthRequest(requestId)) {
        return { error: null };
      }

      applyAuthState(null);
      return { error: null };
    } catch (error) {
      if (!isLatestAuthRequest(requestId)) {
        return { error: null };
      }

      const isAlreadyLoggedOut = error?.status === 401;

      if (isAlreadyLoggedOut) {
        applyAuthState(null);
        return { error: null };
      }

      toast({
        variant: 'destructive',
        title: 'Sign out Failed',
        description: error.message || 'Something went wrong',
      });
      return { error };
    } finally {
      if (isLatestAuthRequest(requestId)) {
        setLoading(false);
      }
    }
  }, [applyAuthState, beginAuthRequest, isLatestAuthRequest, toast]);

  const updateProfile = useCallback(async (updates) => {
    if (!user) {
      toast({ variant: 'destructive', title: 'Not authenticated' });
      return { error: new Error('Not authenticated') };
    }

    try {
      const data = await updateMemberProfile(updates);
      if (data?.profile) {
        const mergedProfile = {
          ...(profile || {}),
          ...data.profile,
          account_info: updates?.account_info
            ? normalizeAccountInfo({
                ...(profile?.account_info || {}),
                ...(data.profile?.account_info || {}),
                ...updates.account_info,
              })
            : (data.profile?.account_info ?? profile?.account_info ?? null),
          profile_info: updates?.profile_info
            ? {
                ...(profile?.profile_info || {}),
                ...(data.profile?.profile_info || {}),
                ...updates.profile_info,
              }
            : (data.profile?.profile_info ?? profile?.profile_info ?? null),
          benefits_info: updates?.benefits_info
            ? {
                ...(profile?.benefits_info || {}),
                ...(data.profile?.benefits_info || {}),
                ...updates.benefits_info,
              }
            : (data.profile?.benefits_info ?? profile?.benefits_info ?? null),
        };
        setProfile(mergedProfile);
      } else {
        await refreshProfile();
      }
      return { error: null };
    } catch (error) {
      toast({ variant: 'destructive', title: 'Update failed', description: error.message });
      return { error };
    }
  }, [profile, refreshProfile, toast, user]);

  const changePassword = useCallback(async (currentPassword, newPassword, confirmPassword) => {
    const requestId = beginAuthRequest();
    setLoading(true);
    try {
      const data = await changeMemberPassword(currentPassword, newPassword, confirmPassword);
      if (!isLatestAuthRequest(requestId)) {
        return { error: null };
      }

      if (data?.user || data?.profile) {
        applyAuthState({
          user: data?.user ?? user,
          session: data?.user ? { user: data.user } : session,
          profile: data?.profile ?? profile,
        });
      } else {
        await refreshProfile();
      }
      toast({
        title: 'Password updated',
        description: 'Your AAC portal password has been changed successfully.',
      });
      return { error: null };
    } catch (error) {
      if (!isLatestAuthRequest(requestId)) {
        return { error: null };
      }

      toast({
        variant: 'destructive',
        title: 'Password Update Failed',
        description: error.message || 'Unable to change your password.',
      });
      return { error };
    } finally {
      if (isLatestAuthRequest(requestId)) {
        setLoading(false);
      }
    }
  }, [applyAuthState, beginAuthRequest, isLatestAuthRequest, profile, refreshProfile, session, toast, user]);

  const value = useMemo(() => ({
    user,
    session,
    profile,
    loading,
    authReady,
    signUp,
    signUpWithProfile,
    signIn,
    signOut,
    updateProfile,
    changePassword,
    refreshProfile,
  }), [user, session, profile, loading, authReady, signUp, signUpWithProfile, signIn, signOut, updateProfile, changePassword, refreshProfile]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};
