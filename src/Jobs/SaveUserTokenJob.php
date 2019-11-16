<?php
namespace Leezj\LaravelApi\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

class SaveUserTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @var Model
     */
    protected $user;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $guard;

    /**
     * Create a new job instance.
     *
     * @param $user
     * @param $token
     * @param $guard
     */
    public function __construct($user, $token, $guard)
    {
        $this->user = $user;
        $this->token = $token;
        $this->guard = $guard;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $uid = $this->user->getKey();
        // User.api.1:LastToken
        $key = sprintf("User.%s-%s:LastToken", $this->guard, $uid);
        Cache::forever($key, $this->token);
    }
}