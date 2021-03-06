<?php
namespace RichardAbear\Syndicate;

use Exception;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Model;
use Faker\Provider\Uuid as ProviderUuid;
use RichardAbear\Syndicate\Models\Organization;
use RichardAbear\Syndicate\Contracts\OrganizationInterface;
use RichardAbear\Syndicate\Events\Organization\MemberInvited;
use RichardAbear\Syndicate\Events\Organization\MemberRemoved;
use RichardAbear\Syndicate\Events\Organization\MemberAcceptedInvite;
use RichardAbear\Syndicate\Exceptions\InvalidInvitationKeyException;

class MemberManager
{
    public Organization $organization;
    
    public Model $manager;

    /**
     * Validates the invitiation token given and fires the necessary evnets
     *
     * @param string $token
     * @return void
     */
    public static function validateInviteToken(string $token): void {
        $key = Crypt::decrypt($token);
        
        $invitation = DB::table('organization_models')->where('invite_key', $key)->first();

        if(! $invitation) {
            throw new InvalidInvitationKeyException();
        }

        $invitation->invite_key = null;
        $invitation->pending = false;

        if($invitation->save()) {
            $organization = Organization::find($invitation->organization_id);
            $user = config('syndicate.user_model')::find($invitation->model_id);
            event(new MemberAcceptedInvite($user, $organization, null));
        } else {
            throw new Exception("Could not validate the invitation.");
        }
    }

    public function __construct(OrganizationInterface $organization, Model $manager)
    {
        $this->organization = $organization;
        $this->manager = $manager;
    }

    /**
     * Remove a model from an organization
     *
     * @param Model $entity
     * @return void
     */
    public function remove(Model $entity)
    {
        $this->organization->members()->detach([$entity->getKey()]);
        event(new MemberRemoved($entity, $this->organization));
    }

    /**
     * Invite a user into the organization
     *
     * @param Model $entity
     * @return void
     */
    public function invite(Model $entity)
    {
        $invite_options = [
            'pending' => config('syndicate.invites', false),
        ];

        if($invite_options['pending']) {
            $invite_options['invite_key'] = Uuid::uuid1();
        }
        $this->organization->members()->attach([$entity->getKey() => $invite_options]);
        event(new MemberInvited($entity, $this->organization, $this->manager));
    }

}
