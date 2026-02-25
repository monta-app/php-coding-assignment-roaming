<?php

namespace App\Dtos;

enum TokenType: string
{
    case RFID = 'RFID';
    case APP_USER = 'APP_USER';
    case OTHER = 'OTHER';
}
