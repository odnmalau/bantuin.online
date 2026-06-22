<?php

namespace App;

enum ExamSessionStatus: string
{
    case InProgress = 'in_progress';
    case Finalized = 'finalized';
    case AutoSubmitted = 'auto_submitted';
    case Expired = 'expired';
}
