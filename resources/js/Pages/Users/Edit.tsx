import UserForm from './Form';

interface Role { id: string; name: string; display_name: string }
interface UserData {
    id: string;
    name: string;
    email: string;
    phone?: string;
    status: string;
    user_roles?: { role: { id: string } }[];
}
interface Props { user: UserData; roles: Role[] }

export default function UserEdit({ user, roles }: Props) {
    return <UserForm user={user} roles={roles} isEdit />;
}
