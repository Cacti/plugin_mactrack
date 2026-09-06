<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 */

/**
 * Resolve one address according to the configured DNS policy.
 *
 * The configured resolver is authoritative when it returns successfully,
 * including a successful response with no PTR records. The system resolver is
 * only a fallback when the configured resolver cannot answer the query.
 *
 * @param mixed         $resolver
 * @param bool          $use_resolver
 * @param string        $ip_address
 * @param callable|null $system_resolver
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
