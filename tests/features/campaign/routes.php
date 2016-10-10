<?php

namespace Tests\Features\Campaign;
use Tests\Conf as Conf;

class Routes {
	const READ = "/campaign/manage";
	const CREATE = "/campaign/create";
	const UPDATE = "/campaign/update";
	const DELETE = "/campaign/delete";
	const EDIT = "/campaign/edit";

	public static function path($p) {
		return Conf::DOMAIN . $p;
	}
}