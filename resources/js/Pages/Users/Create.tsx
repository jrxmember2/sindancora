import UserForm from './Form';

interface Role { id: string; name: string; display_name: string }
interface Props { roles: Role[] }

export default function UserCreate({ roles }: Props) {
    return <UserForm roles={roles} />;
}
