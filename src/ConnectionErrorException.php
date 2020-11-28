<?php

namespace Esign\Flysystem\Ssh2;

use LogicException;

class ConnectionErrorException extends LogicException implements Ssh2AdapterException
{
}
