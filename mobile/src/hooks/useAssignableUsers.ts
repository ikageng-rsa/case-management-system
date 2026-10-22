import { useEffect, useState } from "react";
import { fetchUsers } from "@/api/endpoint/users.api";
import { useAppSelector } from "@/hooks/useAppDispatch";
import { User } from "@/models/index";

export function useAssignableUsers(): User[]{
    const me = useAppSelector(state => state.auth.user);
    const [users, setUsers] = useState<User[]>(me ? [me]: []);

    useEffect(() => {
        fetchUsers()
            .then(list => setUsers(list.length ? list : me ? [me]: []))
            .catch(() => setUsers(me ? [me] : []));
    }, [me]);

    return users;
}