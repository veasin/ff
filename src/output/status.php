<?php
declare(strict_types=1);
namespace nx\output;
enum status: int{
	case Ok = 200;
	case Created = 201;
	case Accepted = 202;
	case NoContent = 204;
	case Moved = 301;
	case Found = 302;
	case NotModified = 304;
	case BadRequest = 400;
	case Unauthorized = 401;
	case Forbidden = 403;
	case NotFound = 404;
	case MethodNotAllowed = 405;
	case Conflict = 409;
	case Gone = 410;
	case TooManyRequests = 429;
	case ServerError = 500;
	case BadGateway = 502;
	case Unavailable = 503;
	case GatewayTimeout = 504;
}
