<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 */

/**
 * Resolve one address according to the configured DNS policy.
 *
 * A NOERROR/NODATA response (no PTR records, but not an exception) falls
 * through to the system resolver the same as a query failure does; only a
 * PTR record actually found in the answer is used without falling back.
 *
 * @param  mixed         $resolver
 * @param  bool          $use_resolver
 * @param  string        $ip_address
 * @param  callable|null $system_resolver
 * @return string
 */
function mactrack_resolve_hostname($resolver, $use_resolver, $ip_address, $system_resolver = null) {
	if ($system_resolver === null) {
		$system_resolver = 'gethostbyaddr';
	}

	$dns_hostname = '';

	if ($use_resolver && is_object($resolver) && is_callable([$resolver, 'query'])) {
		try {
			$response = $resolver->query($ip_address, 'PTR');

			foreach ($response->answer as $answer) {
				if (isset($answer->ptrdname)) {
					$dns_hostname = $answer->ptrdname;

					break;
				}
			}

			if ($dns_hostname === '') {
				$dns_hostname = call_user_func($system_resolver, $ip_address);
			}
		} catch (Net_DNS2_Exception $e) {
			$dns_hostname = call_user_func($system_resolver, $ip_address);
		}
	} else {
		$dns_hostname = call_user_func($system_resolver, $ip_address);
	}

	if ($dns_hostname === false || $dns_hostname === '' || $dns_hostname === $ip_address) {
		return $ip_address;
	}

	return $dns_hostname;
}
